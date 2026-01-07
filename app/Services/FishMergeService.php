<?php

namespace App\Services;

use App\Models\Fish;
use App\Models\FishNote;
use App\Models\FishAudio;
use App\Models\CaptureRecord;
use App\Models\TribalClassification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FishMergeService
{
    /**
     * 預覽合併操作，檢測衝突
     *
     * @param int $targetFishId 主魚類 ID
     * @param array $sourceFishIds 被併入的魚類 ID 陣列
     * @return array 預覽資料與衝突資訊
     */
    public function previewMerge(int $targetFishId, array $sourceFishIds): array
    {
        $target = Fish::with(['notes', 'audios', 'tribalClassifications', 'captureRecords'])
            ->findOrFail($targetFishId);
        
        $sources = Fish::with(['notes', 'audios', 'tribalClassifications', 'captureRecords'])
            ->whereIn('id', $sourceFishIds)
            ->get();
        
        $conflicts = [];
        $summary = [
            'notes_to_transfer' => 0,
            'audios_to_transfer' => 0,
            'records_to_transfer' => 0,
            'classifications_to_transfer' => 0,
            'classifications_conflicts' => 0,
        ];
        
        foreach ($sources as $source) {
            // 統計要轉移的資料
            $summary['notes_to_transfer'] += $source->notes->count();
            $summary['audios_to_transfer'] += $source->audios->count();
            $summary['records_to_transfer'] += $source->captureRecords->count();
            
            // 檢測部落分類衝突
            $targetTribes = $target->tribalClassifications->pluck('tribe')->toArray();
            foreach ($source->tribalClassifications as $classification) {
                if (in_array($classification->tribe, $targetTribes)) {
                    // 有衝突
                    $conflicts['tribal_classifications'][] = [
                        'tribe' => $classification->tribe,
                        'source_fish_id' => $source->id,
                        'target_data' => $target->tribalClassifications
                            ->where('tribe', $classification->tribe)
                            ->first(),
                        'source_data' => $classification,
                        'resolution' => 'keep_target', // 策略：保留主魚類
                    ];
                    $summary['classifications_conflicts']++;
                } else {
                    // 無衝突
                    $summary['classifications_to_transfer']++;
                }
            }
        }
        
        // 扁平化衝突資料為前端期望的格式
        $flattenedConflicts = [];
        foreach ($conflicts as $conflictType => $conflictItems) {
            foreach ($conflictItems as $conflict) {
                $flattenedConflicts[] = [
                    'type' => $conflictType,
                    'message' => $this->getConflictMessage($conflictType, $conflict),
                ];
            }
        }
        
        return [
            'target' => $target,
            'sources' => $sources,
            'conflicts' => $flattenedConflicts,
            'summary' => $summary,
            // 為前端提供正確的欄位名稱
            'notes_count' => $summary['notes_to_transfer'],
            'audios_count' => $summary['audios_to_transfer'],
            'capture_records_count' => $summary['records_to_transfer'],
        ];
    }
    
    /**
     * 執行魚類合併
     *
     * @param int $targetFishId 主魚類 ID
     * @param array $sourceFishIds 被併入的魚類 ID 陣列
     * @return array 合併結果
     * @throws \Exception
     */
    public function mergeFish(int $targetFishId, array $sourceFishIds): array
    {
        return DB::transaction(function () use ($targetFishId, $sourceFishIds) {
            $target = Fish::findOrFail($targetFishId);
            $mergeResults = [
                'target_fish_id' => $targetFishId,
                'merged_fish_ids' => [],
                'transferred' => [
                    'notes' => 0,
                    'audios' => 0,
                    'capture_records' => 0,
                    'tribal_classifications' => 0,
                    'fish_size' => false,
                ],
                'conflicts_resolved' => [
                    'tribal_classifications' => 0,
                    'fish_size' => 0,
                ],
            ];
            
            foreach ($sourceFishIds as $sourceFishId) {
                $source = Fish::findOrFail($sourceFishId);
                
                // 1. 轉移筆記
                $notesCount = FishNote::where('fish_id', $sourceFishId)
                    ->update(['fish_id' => $targetFishId]);
                $mergeResults['transferred']['notes'] += $notesCount;
                
                // 2. 轉移發音
                $audiosCount = FishAudio::where('fish_id', $sourceFishId)
                    ->update(['fish_id' => $targetFishId]);
                $mergeResults['transferred']['audios'] += $audiosCount;
                
                // 3. 轉移捕獲紀錄
                $recordsCount = CaptureRecord::where('fish_id', $sourceFishId)
                    ->update(['fish_id' => $targetFishId]);
                $mergeResults['transferred']['capture_records'] += $recordsCount;
                
                // 4. 處理部落分類（保留主魚類，刪除衝突的被併入資料）
                $sourceClassifications = TribalClassification::where('fish_id', $sourceFishId)->get();
                foreach ($sourceClassifications as $classification) {
                    $conflict = TribalClassification::where('fish_id', $targetFishId)
                        ->where('tribe', $classification->tribe)
                        ->exists();
                    
                    if ($conflict) {
                        // 有衝突：保留主魚類，刪除被併入的
                        Log::info("Tribal classification conflict detected and resolved", [
                            'tribe' => $classification->tribe,
                            'target_fish_id' => $targetFishId,
                            'source_fish_id' => $sourceFishId,
                            'action' => 'deleted_source',
                        ]);
                        $classification->delete();
                        $mergeResults['conflicts_resolved']['tribal_classifications']++;
                    } else {
                        // 無衝突：轉移
                        $classification->update(['fish_id' => $targetFishId]);
                        $mergeResults['transferred']['tribal_classifications']++;
                    }
                }
                
                // 5. 軟刪除來源魚類（已移除 FishSize 處理）
                $source->delete();
                $mergeResults['merged_fish_ids'][] = $sourceFishId;
                
                Log::info("Fish merged successfully", [
                    'target_fish_id' => $targetFishId,
                    'source_fish_id' => $sourceFishId,
                ]);
            }
            
            return $mergeResults;
        });
    }
    
    /**
     * 驗證合併操作的合法性
     *
     * @param int $targetFishId
     * @param array $sourceFishIds
     * @return array 驗證結果
     */
    public function validateMerge(int $targetFishId, array $sourceFishIds): array
    {
        $errors = [];
        
        // 檢查目標魚類是否存在
        if (!Fish::where('id', $targetFishId)->exists()) {
            $errors[] = "目標魚類 ID {$targetFishId} 不存在";
        }
        
        // 檢查來源魚類是否存在
        $existingSourceIds = Fish::whereIn('id', $sourceFishIds)->pluck('id')->toArray();
        $missingIds = array_diff($sourceFishIds, $existingSourceIds);
        if (!empty($missingIds)) {
            $errors[] = "以下魚類 ID 不存在: " . implode(', ', $missingIds);
        }
        
        // 檢查是否嘗試合併自己
        if (in_array($targetFishId, $sourceFishIds)) {
            $errors[] = "無法將魚類合併到自己";
        }
        
        // 檢查至少要有一條被併入的魚類
        if (empty($sourceFishIds)) {
            $errors[] = "至少需要選擇一條要併入的魚類";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * 生成衝突訊息
     */
    private function getConflictMessage(string $conflictType, array $conflict): string
    {
        switch ($conflictType) {
            case 'tribal_classifications':
                return "魚類 #{$conflict['source_fish_id']} 與主要魚類都有部落「{$conflict['tribe']}」的分類資料";
            
            case 'fish_size':
                return "魚類 #{$conflict['source_fish_id']} 與主要魚類都有體型資料";
            
            default:
                return "發現資料衝突";
        }
    }
}

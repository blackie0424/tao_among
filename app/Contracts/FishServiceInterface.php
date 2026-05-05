<?php

namespace App\Contracts;

use App\Models\Fish;

interface FishServiceInterface
{
    public function getAllFishes();

    public function getFishesBySince($since);

    public function getFishById($id);

    public function getFishByIdAndLocate($id, $locate);

    public function assignImageUrls($fishes);

    public function decorateFishMedia(Fish $fish): Fish;

    public function getFishDetails(int $id): array;

    /**
     * 從 LINE Bot 建立魚類記錄（含批次捕獲記錄）
     *
     * @param string|null $name 魚類名稱，null 時使用預設名稱
     * @param string[] $filenames 已上傳至 S3 的圖片檔名陣列（basename only）
     */
    public function createFishFromLine(?string $name, array $filenames): Fish;

    /**
     * 從 LINE Bot 對既有魚種批次建立捕獲紀錄
     *
     * @param int $fishId 既有魚種 ID
     * @param string[] $filenames 已上傳至 S3 的圖片檔名陣列（basename only）
     * @param array{tribe:string, location:string, capture_method:string, capture_date:string, notes:string} $captureData
     * @return array<int, \App\Models\CaptureRecord>
     */
    public function createCaptureRecordsFromLine(int $fishId, array $filenames, array $captureData): array;
}

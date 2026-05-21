<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferenceKnowledgeRequest;
use App\Http\Requests\UpdateReferenceKnowledgeRequest;
use App\Models\Fish;
use App\Models\Reference;
use App\Models\ReferenceKnowledge;
use App\Services\FishService;
use App\Services\ReferenceKnowledgeService;
use Exception;
use Inertia\Inertia;
use Inertia\Response;

class ReferenceKnowledgeController extends BaseController
{
    public function __construct(
        protected FishService $fishService,
        protected ReferenceKnowledgeService $referenceKnowledgeService
    ) {
    }

    public function index($fishId): Response|\Illuminate\Http\RedirectResponse
    {
        try {
            $fish = $this->getFish($fishId);

            return Inertia::render('ReferenceKnowledge/Index', [
                'fish' => $fish,
                'knowledge' => ReferenceKnowledge::query()
                    ->with(['reference', 'creator'])
                    ->where('fish_id', $fishId)
                    ->orderByDesc('created_at')
                    ->paginate(20)
                    ->withQueryString(),
            ]);
        } catch (Exception $e) {
            return $this->handleControllerError($e, '無法載入文獻知識列表');
        }
    }

    public function create($fishId): Response|\Illuminate\Http\RedirectResponse
    {
        try {
            return Inertia::render('ReferenceKnowledge/Create', [
                'fish' => $this->getFish($fishId),
                'references' => $this->getEnabledReferences(),
            ]);
        } catch (Exception $e) {
            return $this->handleControllerError($e, '無法載入新增文獻知識頁面');
        }
    }

    public function edit($fishId, $knowledgeId): Response|\Illuminate\Http\RedirectResponse
    {
        try {
            return Inertia::render('ReferenceKnowledge/Edit', [
                'fish' => $this->getFish($fishId),
                'knowledge' => $this->findKnowledge($fishId, $knowledgeId)->load('reference'),
                'references' => $this->getEnabledReferences(),
            ]);
        } catch (Exception $e) {
            return $this->handleControllerError($e, '無法載入編輯文獻知識頁面');
        }
    }

    public function store(StoreReferenceKnowledgeRequest $request, $fishId)
    {
        try {
            return $this->executeWithTransaction(function () use ($request, $fishId) {
                $fish = $this->findResourceOrFail(Fish::class, $fishId, '魚類');

                $this->referenceKnowledgeService->createForFish(
                    $fish,
                    $request->validated(),
                    $request->user()
                );

                return redirect("/fish/{$fishId}/reference-knowledge")
                    ->with('success', '文獻知識已成功建立');
            }, 'reference knowledge creation');
        } catch (Exception $e) {
            return $this->handleControllerError($e, '建立文獻知識失敗');
        }
    }

    public function update(UpdateReferenceKnowledgeRequest $request, $fishId, $knowledgeId)
    {
        try {
            return $this->executeWithTransaction(function () use ($request, $fishId, $knowledgeId) {
                $knowledge = $this->findKnowledge($fishId, $knowledgeId);

                $this->referenceKnowledgeService->update($knowledge, $request->validated());

                return redirect("/fish/{$fishId}/reference-knowledge")
                    ->with('success', '文獻知識已成功更新');
            }, 'reference knowledge update');
        } catch (Exception $e) {
            return $this->handleControllerError($e, '更新文獻知識失敗');
        }
    }

    public function destroy($fishId, $knowledgeId)
    {
        try {
            return $this->executeWithTransaction(function () use ($fishId, $knowledgeId) {
                $knowledge = $this->findKnowledge($fishId, $knowledgeId);
                $knowledge->delete();

                return redirect("/fish/{$fishId}/reference-knowledge")
                    ->with('success', '文獻知識已成功刪除');
            }, 'reference knowledge deletion');
        } catch (Exception $e) {
            return $this->handleControllerError($e, '刪除文獻知識失敗');
        }
    }

    private function getFish(int|string $fishId): Fish
    {
        $fish = $this->findResourceOrFail(Fish::class, $fishId, '魚類');

        return $this->fishService->assignImageUrls([$fish])[0];
    }

    private function getEnabledReferences()
    {
        return Reference::query()
            ->enabled()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function findKnowledge(int|string $fishId, int|string $knowledgeId): ReferenceKnowledge
    {
        $this->findResourceOrFail(Fish::class, $fishId, '魚類');

        return $this->findRelatedResourceOrFail(ReferenceKnowledge::class, [
            'fish_id' => $fishId,
            'id' => $knowledgeId,
        ], '文獻知識');
    }
}


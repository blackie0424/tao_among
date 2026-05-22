<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferenceRequest;
use App\Http\Requests\UpdateReferenceRequest;
use App\Models\Reference;
use App\Services\ReferenceService;
use Inertia\Inertia;
use Inertia\Response;

class ReferenceController extends BaseController
{
    public function __construct(
        protected ReferenceService $referenceService
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Admin/References/Index', [
            'references' => Reference::query()
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/References/Create');
    }

    public function edit(Reference $reference): Response
    {
        return Inertia::render('Admin/References/Edit', [
            'reference' => $reference,
        ]);
    }

    public function store(StoreReferenceRequest $request)
    {
        return $this->executeWithTransaction(function () use ($request) {
            $this->referenceService->create($request->validated());

            return redirect('/admin/references')->with('success', '文獻已成功建立');
        }, 'reference creation');
    }

    public function update(UpdateReferenceRequest $request, Reference $reference)
    {
        return $this->executeWithTransaction(function () use ($request, $reference) {
            $this->referenceService->update($reference, $request->validated());

            return redirect('/admin/references')->with('success', '文獻已成功更新');
        }, 'reference update');
    }
}


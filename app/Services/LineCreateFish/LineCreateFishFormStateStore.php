<?php

namespace App\Services\LineCreateFish;

use App\Services\LineBatchCapture\LineBatchCaptureStateStore;

class LineCreateFishFormStateStore extends LineBatchCaptureStateStore
{
    public function __construct()
    {
        parent::__construct('create_fish_form');
    }
}

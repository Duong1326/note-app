<?php

namespace App\Http\Controllers\Note;

use App\Http\Controllers\Controller;

class NoteControler extends Controller
{
    public function __construct(
        private NoteService $noteService,
        private LabelService $labelService,
        private PreferenceService $prefService,
    ) {
    }
}
<?php

namespace App\Domain\Evaluations\Enums;

/**
 * SUBMITTED = avaliação finalizada pelo jurado. Correção só via REVISION_REQUESTED (o jurado reenvia).
 */
enum EvaluationStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case RevisionRequested = 'REVISION_REQUESTED';
}

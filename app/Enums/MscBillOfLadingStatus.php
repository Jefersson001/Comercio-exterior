<?php

namespace App\Enums;

enum MscBillOfLadingStatus: string
{
    case Draft = 'Borrador';
    case Released = 'BL LIBERADO OK';
    case ReleasedAtOrigin = 'LIBERADO EN ORIGEN';
    case PendingRelease = 'POR LIBERACION BL';
    case PendingReleaseWithoutDocument = 'POR LIBERACION BLSIN DOCUMENTO';
}

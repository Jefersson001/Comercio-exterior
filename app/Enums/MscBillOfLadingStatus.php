<?php

namespace App\Enums;

enum MscBillOfLadingStatus: string
{
    case Released = 'BL LIBERADO OK';
    case PendingRelease = 'POR LIBERACION BL';
    case PendingReleaseWithoutDocument = 'POR LIBERACION BLSIN DOCUMENTO';
}

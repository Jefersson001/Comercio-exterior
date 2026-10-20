<?php

namespace App\Enums;

enum MscShipmentStatus: string
{
    case Draft = 'Borrador';
    case InBond = 'IN BOND';
    case Navegando = 'NAVEGANDO';
    case PorDespacho = 'POR DESPACHO';
    case Puerto = 'PUERTO';
    case Recepcion = 'RECEPCION';
    case Reconocimiento = 'RECONOCIMIENTO';
}

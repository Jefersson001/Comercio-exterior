<?php

namespace App\Enums;

enum MscShipmentStatus: string
{
    case InBond = 'IN BOND';
    case Navegando = 'NAVEGANDO';
    case PorDespacho = 'POR DESPACHO';
    case Puerto = 'PUERTO';
    case Recepcion = 'RECEPCION';
    case Reconocimiento = 'RECONOCIMIENTO';
}

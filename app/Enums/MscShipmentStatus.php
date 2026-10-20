<?php

namespace App\Enums;

enum MscShipmentStatus: string
{
    case Navegando = 'NAVEGANDO';
    case Puerto = 'PUERTO';
    case Registro = 'REGISTRO';
    case InBond = 'IN BOND';
    case PorDespacho = 'POR DESPACHO';
    case Recepcion = 'RECEPCION';
    case Reconocimiento = 'RECONOCIMIENTO';

    // Conserva la lectura de embarques antiguos sin ofrecer Borrador como opción.
    case Draft = 'Borrador';

    /** @return list<self> */
    public static function options(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status !== self::Draft));
    }
}

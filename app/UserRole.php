<?php

namespace App;

enum UserRole: string
{
    case Administrator = 'administrador';
    case User = 'usuario';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::User => 'Usuario',
        };
    }
}

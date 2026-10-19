<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser un texto.',
    'email' => 'Ingresa un correo electrónico válido.',
    'unique' => 'Este :attribute ya está registrado.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'enum' => 'El :attribute seleccionado no es válido.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'exists' => 'El :attribute seleccionado no existe.',
    'date_format' => 'El campo :attribute debe contener una fecha válida (:format).',
    'after_or_equal' => 'El campo :attribute debe ser una fecha igual o posterior a :date.',
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.', 'numeric' => 'El campo :attribute debe ser al menos :min.'],
    'max' => ['string' => 'El campo :attribute no debe superar :max caracteres.', 'numeric' => 'El campo :attribute no debe superar :max.'],
    'attributes' => [
        'name' => 'nombre', 'last_name' => 'apellido', 'email' => 'correo electrónico',
        'password' => 'contraseña', 'role' => 'rol',
    ],
];

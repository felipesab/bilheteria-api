<?php

use App\Support\CalculadoraDePreco;

test('aplica desconto de 10% ao preço', function () {
    $calculadora = new CalculadoraDePreco;

    expect($calculadora->comDesconto(100.0, 10.0))->toBe(80.0);
});

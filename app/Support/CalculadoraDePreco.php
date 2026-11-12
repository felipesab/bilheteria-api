<?php

namespace App\Support;

class CalculadoraDePreco
{
    /**
     * Aplica um desconto percentual ao preço informado.
     */
    public function comDesconto(float $preco, float $percentual): float
    {
        $precoFinal = $preco - ($preco * $percentual / 100);

        return number_format($precoFinal, 2);
    }
}

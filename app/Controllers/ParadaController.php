<?php

declare(strict_types=1);

namespace SFATSA\Controllers;

final class ParadaController extends CatalogoHU04Controller
{
    protected const MODULO = 'paradas';
    protected const ID = 'id_parada';
    protected const CAMPOS = ['nombre', 'referencia'];
}

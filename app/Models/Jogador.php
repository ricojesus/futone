<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jogador extends Model
{
    protected $table = 'jogadores';

    protected $fillable = [
        'nome',
        'posicao',
        'nacionalidade',
        'idade',
        'forca',
        'foto',
    ];

    public static array $posicoes = [
        'goleiro' => 'Goleiro',
        'defesa'  => 'Defesa',
        'meio'    => 'Meio',
        'ataque'  => 'Ataque',
    ];
}

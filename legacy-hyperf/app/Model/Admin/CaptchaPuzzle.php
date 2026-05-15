<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Hyperf\Database\Model\SoftDeletes;

class CaptchaPuzzle extends Model
{
    use SoftDeletes;

    protected ?string $table = 'admin_captcha_puzzles';

    protected array $fillable = [
        'background_path',
        'slider_path',
        'mask_path',
        'answer_x',
        'answer_y',
        'width',
        'height',
        'status',
        'hint',
    ];

    protected array $casts = [
        'id' => 'integer',
        'answer_x' => 'float',
        'answer_y' => 'float',
        'width' => 'integer',
        'height' => 'integer',
        'status' => 'integer',
    ];
}


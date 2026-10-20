<?php

namespace Arzcode\SharedSecrets\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class LanguageModel extends Model
{
    public $timestamps = false;
    protected $table = 'languages';
    protected $guarded = [];
}

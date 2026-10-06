<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Contest extends Model { protected $fillable = ['name','slug','required_hashtag','starts_at','ends_at','is_active']; protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime','is_active'=>'boolean']; } public function submissions(): HasMany { return $this->hasMany(Submission::class); } }

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SubmissionMetricSnapshot extends Model { protected $fillable = ['views','likes','comments','captured_at','provider']; protected function casts(): array { return ['captured_at'=>'datetime','views'=>'integer','likes'=>'integer','comments'=>'integer']; } public function submission(): BelongsTo { return $this->belongsTo(Submission::class); } }

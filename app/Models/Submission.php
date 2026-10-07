<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Submission extends Model { protected $fillable = ['contest_id','source','external_reference','campaign_reference','participant_name','participant_email','platform','original_url','canonical_url','external_id','author','username','caption','published_at','is_public','hashtag_valid','views','likes','comments','status','validation_message','provider','raw_metadata','last_checked_at']; protected function casts(): array { return ['published_at'=>'datetime','last_checked_at'=>'datetime','is_public'=>'boolean','hashtag_valid'=>'boolean','views'=>'integer','likes'=>'integer','comments'=>'integer','raw_metadata'=>'array']; } public function contest(): BelongsTo { return $this->belongsTo(Contest::class); } public function metricSnapshots(): HasMany { return $this->hasMany(SubmissionMetricSnapshot::class); } }

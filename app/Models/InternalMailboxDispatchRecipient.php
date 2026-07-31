<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InternalMailboxDispatchRecipient extends Model { protected $guarded=[]; public function dispatch():BelongsTo{return $this->belongsTo(InternalMailboxDispatch::class,'internal_mailbox_dispatch_id');} public function user():BelongsTo{return $this->belongsTo(User::class);} }

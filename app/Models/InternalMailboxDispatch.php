<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class InternalMailboxDispatch extends Model {
    protected $guarded=[];
    protected function casts():array{return['scheduled_for'=>'datetime','sent_at'=>'datetime','failed_at'=>'datetime'];}
    public function sender():BelongsTo{return $this->belongsTo(User::class,'sender_user_id');}
    public function company():BelongsTo{return $this->belongsTo(Company::class);}
    public function project():BelongsTo{return $this->belongsTo(Project::class);}
    public function parent():BelongsTo{return $this->belongsTo(self::class,'parent_dispatch_id');}
    public function parentMessage():BelongsTo{return $this->belongsTo(CollaborationMessage::class,'parent_message_id');}
    public function recipients():HasMany{return $this->hasMany(InternalMailboxDispatchRecipient::class);}
    public function attachments():HasMany{return $this->hasMany(InternalMailboxAttachment::class);}
    public function messages():HasMany{return $this->hasMany(CollaborationMessage::class);}
    public function isParticipant(User $user): bool
    {
        return (int) $this->sender_user_id === (int) $user->id
            || $this->recipients()->where('user_id', $user->id)->exists();
    }
}

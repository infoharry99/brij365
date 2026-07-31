<?php
namespace App\Http\Requests\Mailbox;
use App\Application\Mailbox\Data\UnifiedComposeData;
use App\Models\CollaborationMessage;
use App\Models\MailboxAccount;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
class StoreUnifiedMailboxComposeRequest extends FormRequest {
    public function authorize():bool{return $this->user()?->can('create',CollaborationMessage::class)===true;}
    public function rules():array{$sending=in_array($this->input('intent'),['send','schedule'],true);$max=(int)config('mailbox.attachment_max_kb',25600);return[
        'sender_key'=>['required','string',function($attribute,$value,$fail){if($value!=='internal'&&!preg_match('/^external:\d+$/',$value))$fail('Select an available sender.');}],
        'intent'=>['required',Rule::in(['draft','send','schedule'])],'client_token'=>['required','uuid'],'lock_version'=>['nullable','integer','min:1'],'project_id'=>['nullable','integer',Rule::exists('projects','id')],'parent_message_id'=>['nullable','integer',Rule::exists('collaboration_messages','id')],
        'to_user_ids'=>[$sending&&$this->input('sender_key')==='internal'?'required':'nullable','array','max:20'],'to_user_ids.*'=>['integer','distinct',Rule::exists('users','id')],
        'cc_user_ids'=>['nullable','array','max:20'],'cc_user_ids.*'=>['integer','distinct',Rule::exists('users','id')],'bcc_user_ids'=>['nullable','array','max:20'],'bcc_user_ids.*'=>['integer','distinct',Rule::exists('users','id')],
        'to'=>[$sending&&str_starts_with((string)$this->input('sender_key'),'external:')?'required':'nullable','array','max:50'],'to.*'=>['email:rfc'],'cc'=>['nullable','array','max:50'],'cc.*'=>['email:rfc'],'bcc'=>['nullable','array','max:50'],'bcc.*'=>['email:rfc'],
        'subject'=>[$sending?'required':'nullable','string','max:255'],'body'=>[$sending?'required':'nullable','string','max:200000'],'priority'=>['nullable',Rule::in(['low','normal','high','critical'])],'scheduled_for'=>['required_if:intent,schedule','nullable','date','after:now'],
        'attachments'=>['nullable','array','max:10'],'attachments.*'=>['file','max:'.$max,'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip'],'remove_attachment_ids'=>['nullable','array','max:10'],'remove_attachment_ids.*'=>['integer','min:1'],
    ];}
    protected function prepareForValidation():void{$seen=[];foreach(['to','cc','bcc']as$field){$value=$this->input($field);if(is_string($value))$value=preg_split('/[,;\s]+/',$value)?:[];$normalized=collect((array)$value)->map(fn($item)=>strtolower(trim($item)))->filter()->reject(function($item)use(&$seen){if(isset($seen[$item]))return true;$seen[$item]=true;return false;})->values()->all();$this->merge([$field=>$normalized]);}foreach(['to_user_ids','cc_user_ids','bcc_user_ids']as$field)$this->merge([$field=>array_values(array_unique(array_map('intval',(array)$this->input($field,[]))))]);}
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $actor = $this->user();
            $sender = (string) $this->input('sender_key');

            if ($this->filled('project_id')) {
                $project = Project::find($this->integer('project_id'));
                if (! $project || ! app(CompanyScopeService::class)->allows($actor, $project->company_id)) {
                    $validator->errors()->add('project_id', 'The selected project is not available to you.');
                }
            }

            if (str_starts_with($sender, 'external:')) {
                $id = (int) str($sender)->after(':')->toString();
                $account = MailboxAccount::find($id);
                if (! $account
                    || (int) $account->user_id !== (int) $actor->id
                    || (int) $account->company_id !== (int) $actor->company_id
                    || $account->status !== 'active') {
                    $validator->errors()->add('sender_key', 'The selected email account is not available.');
                }

                return;
            }

            $ids = array_values(array_unique(array_merge(
                $this->input('to_user_ids', []),
                $this->input('cc_user_ids', []),
                $this->input('bcc_user_ids', []),
            )));

            if (count($ids) > 20) {
                $validator->errors()->add('to_user_ids', 'A maximum of 20 internal recipients is allowed.');
            }

            $externalRoleSlugs = ['buyer', 'channel_partner', 'executive_partner_broker'];
            $users = \App\Models\User::with('role')->whereIn('id', $ids)->get();
            foreach ($users as $user) {
                if ((int) $user->id === (int) $actor->id) {
                    $validator->errors()->add('to_user_ids', 'You cannot send an internal message to yourself.');
                }

                if ($user->status !== 'active'
                    || in_array($user->role?->slug, $externalRoleSlugs, true)
                    || ! app(CompanyScopeService::class)->allows($actor, $user->company_id)) {
                    $validator->errors()->add('to_user_ids', 'Every internal recipient must be an active employee available to your company.');
                }
            }
        }];
    }
    public function toDto():UnifiedComposeData{$v=$this->validated();return new UnifiedComposeData($this->user(),$v['sender_key'],$v['intent'],$v['client_token'],$v['lock_version']??null,$v['project_id']??null,$v['parent_message_id']??null,$v['to_user_ids']??[],$v['cc_user_ids']??[],$v['bcc_user_ids']??[],$v['to']??[],$v['cc']??[],$v['bcc']??[],$v['subject']??null,$v['body']??null,$v['priority']??'normal',$v['scheduled_for']??null,$v['attachments']??[],$v['remove_attachment_ids']??[],$this);}
}

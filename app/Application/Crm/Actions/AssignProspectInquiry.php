<?php
namespace App\Application\Crm\Actions;
use App\Application\Crm\Data\CrmCommandData;
use App\Models\ProspectInquiry;
use App\Services\Crm\ProspectInquiryService;
final class AssignProspectInquiry { public function __construct(private readonly ProspectInquiryService $inquiries) {} public function execute(ProspectInquiry $inquiry, CrmCommandData $command): ProspectInquiry { return $this->inquiries->assign($inquiry, $command->attributes, $command->actor, $command->request); } }

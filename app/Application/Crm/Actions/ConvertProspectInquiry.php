<?php
namespace App\Application\Crm\Actions;
use App\Application\Crm\Data\CrmCommandData;
use App\Models\ProspectInquiry;
use App\Services\Crm\ProspectInquiryService;
final class ConvertProspectInquiry { public function __construct(private readonly ProspectInquiryService $inquiries) {} public function execute(ProspectInquiry $inquiry, CrmCommandData $command): ProspectInquiry { return $this->inquiries->convertToLead($inquiry, $command->attributes, $command->actor, $command->request); } }

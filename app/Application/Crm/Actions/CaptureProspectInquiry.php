<?php
namespace App\Application\Crm\Actions;
use App\Models\ProspectInquiry;
use App\Services\Crm\ProspectInquiryService;
use Illuminate\Http\Request;
final class CaptureProspectInquiry { public function __construct(private readonly ProspectInquiryService $inquiries) {} public function execute(array $attributes, Request $request): ProspectInquiry { return $this->inquiries->capturePublic($attributes, $request); } }

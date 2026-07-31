<?php
namespace App\Application\Mailbox\Data;
use Illuminate\Database\Eloquent\Model;
final readonly class UnifiedComposeResult { public function __construct(public string $source,public string $state,public Model $record){} }

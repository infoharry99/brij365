<x-ui.card id="logic-variable-packs" title="Governed variable packs" eyebrow="Effective-dated configuration" meta="{{ count($page->variablePacks) }} versions">
    @if (count($page->variablePacks) === 0)
        <x-ui.empty-state
            title="No governed variable packs found"
            description="Create a draft through the approved settings workflow. No default statutory rate will be inferred or activated."
            icon="fa-shield-halved"
        />
    @else
        <x-ui.responsive-register label="Governed variable pack versions">
            <x-slot:desktop>
                <table class="blade-data-table">
                    <caption class="sr-only">Governed statutory, payroll, attendance and roster variable packs</caption>
                    <thead>
                        <tr>
                            <th scope="col">Variable pack</th>
                            <th scope="col">Version</th>
                            <th scope="col">Status</th>
                            <th scope="col">Effective period</th>
                            <th scope="col">Official source</th>
                            <th scope="col">Checksum</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($page->variablePacks as $pack)
                            <tr>
                                <td><strong>{{ $pack->label }}</strong><small>{{ $pack->settingKey }} &middot; {{ $pack->domain }}</small></td>
                                <td>v{{ $pack->version }}</td>
                                <td>
                                    <x-ui.badge>{{ $pack->status }}</x-ui.badge>
                                    @if ($pack->requiresVerification)
                                        <small>{{ $pack->verified ? 'Official source verified' : 'Verification required' }}</small>
                                    @endif
                                </td>
                                <td>{{ $pack->effectivePeriod }}</td>
                                <td><strong>{{ $pack->sourceAuthority }}</strong><small>{{ $pack->sourceReference }}</small></td>
                                <td><code>{{ $pack->checksum }}</code></td>
                                <td>
                                    <div class="blade-inline-actions">
                                        @if ($pack->reviewUrl)
                                            <x-ui.action :href="$pack->reviewUrl">Review</x-ui.action>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @if (count($pack->variables) > 0)
                                <tr class="logic-variable-detail-row">
                                    <td colspan="7">
                                        <dl class="logic-variable-grid" aria-label="{{ $pack->label }} normalized variables">
                                            @foreach ($pack->variables as $variable)
                                                <div><dt>{{ $variable['label'] }}</dt><dd>{{ $variable['value'] }}</dd></div>
                                            @endforeach
                                        </dl>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </x-slot:desktop>
            <x-slot:mobile>
                <div class="b360-mobile-register">
                    @foreach ($page->variablePacks as $pack)
                        <article>
                            <strong>{{ $pack->label }}</strong>
                            <span>v{{ $pack->version }} &middot; {{ $pack->status }}</span>
                            <small>{{ $pack->effectivePeriod }}</small>
                            <small>
                                @if ($pack->requiresVerification)
                                    {{ $pack->verified ? 'Verified source' : 'Verification required' }} &middot;
                                @endif
                                {{ $pack->checksum }}
                            </small>
                            @if (count($pack->variables) > 0)
                                <dl class="logic-variable-grid" aria-label="{{ $pack->label }} normalized variables">
                                    @foreach ($pack->variables as $variable)
                                        <div><dt>{{ $variable['label'] }}</dt><dd>{{ $variable['value'] }}</dd></div>
                                    @endforeach
                                </dl>
                            @endif
                            @if ($pack->reviewUrl)
                                <x-ui.action :href="$pack->reviewUrl">Review</x-ui.action>
                            @endif
                        </article>
                    @endforeach
                </div>
            </x-slot:mobile>
        </x-ui.responsive-register>
    @endif
</x-ui.card>

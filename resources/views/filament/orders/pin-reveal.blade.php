{{-- Plaintext PIN for staff. Only rendered after LogPinAccess has written the audit row (PinsRelationManager "Show PIN"). Inline styles: the panel has no custom Tailwind theme. --}}
<dl style="display: grid; gap: 1rem;">
    <div>
        <dt style="font-size: 0.875rem; opacity: 0.7;">PIN</dt>
        <dd style="margin-top: 0.25rem; font-family: ui-monospace, monospace; font-size: 1.5rem; font-weight: 600; letter-spacing: 0.08em;">{{ $pin->pin }}</dd>
    </div>
    <div>
        <dt style="font-size: 0.875rem; opacity: 0.7;">Serial number</dt>
        <dd style="margin-top: 0.25rem; font-family: ui-monospace, monospace; font-size: 1.125rem;">{{ $pin->serial_number ?? '—' }}</dd>
    </div>
</dl>

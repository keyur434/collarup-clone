<div id="bulkImportPreview" class="bulk-import-preview hidden" style="margin-top:16px;padding:16px;border:1px solid var(--border);border-radius:8px;background:var(--gray-50,#f8fafc)">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px">
        <div>
            <h3 style="font-size:0.95rem;margin:0 0 4px">Import preview</h3>
            <p class="hint" id="bulkPreviewSummary" style="margin:0">Review parsed fields. Fix empty rows before adding.</p>
        </div>
        <button type="button" class="btn btn-outline btn-sm" id="bulkPreviewClear">Clear preview</button>
    </div>
    <div class="table-wrap" style="max-height:420px;overflow:auto">
        <table class="data-table" id="bulkPreviewTable">
            <thead>
                <tr>
                    <th style="width:36px">Add</th>
                    <th>Source</th>
                    <th>First name</th>
                    <th>Last name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Exp.</th>
                    <th>Company</th>
                </tr>
            </thead>
            <tbody id="bulkPreviewBody"></tbody>
        </table>
    </div>
    <input type="hidden" name="bulk_preview_confirmed" id="bulk_preview_confirmed" value="">
    <input type="hidden" name="bulk_items" id="bulk_items" value="">
</div>

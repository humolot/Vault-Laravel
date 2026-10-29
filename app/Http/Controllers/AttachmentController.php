<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Item;
use App\Services\VaultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function __construct(private VaultService $vault)
    {
    }

    private function access(Item $item, string $min): string
    {
        $a = $this->vault->resolveAccess($item, auth()->user());
        abort_if(! $a || VaultService::RANK[$a['permission']] < VaultService::RANK[$min], 403);
        return $a['key'];
    }

    public function store(Request $request, Item $item)
    {
        $key = $this->access($item, 'edit');
        $request->validate([
            'files' => 'required|array|max:10',
            'files.*' => 'file|max:' . config('vault.attachment_max_kb') . '|mimes:' . config('vault.attachment_mimes'),
        ]);
        foreach ($request->file('files') as $file) {
            $this->vault->storeAttachment($item, $request->user(), $key, $file);
        }
        $item->touch();
        return back()->with('status', __('app.uploaded'));
    }

    public function download(Request $request, Attachment $attachment)
    {
        $item = Item::findOrFail($attachment->item_id);
        $key = $this->access($item, 'view');
        $meta = $this->vault->attachmentMeta($attachment, $key);
        $content = $this->vault->attachmentContent($attachment, $key);
        AuditLog::record('attachment.download', $request->user()->id, $item->id, ['attachment' => $attachment->id]);

        $inline = $request->boolean('inline') && in_array($meta['mime'], ['application/pdf', 'image/png', 'image/jpeg', 'image/webp', 'image/gif', 'text/plain'], true);
        $name = str_replace(['"', "\r", "\n"], '', $meta['name']);

        return response($content, 200, [
            'Content-Type' => $meta['mime'],
            'Content-Length' => strlen($content),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . addslashes($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => $meta['mime'] === 'application/pdf'
                ? "default-src 'self'; object-src 'self'; frame-ancestors 'none'"
                : "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        $item = Item::findOrFail($attachment->item_id);
        $this->access($item, 'edit');
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();
        AuditLog::record('attachment.delete', $request->user()->id, $item->id);
        return back()->with('status', __('app.deleted'));
    }
}

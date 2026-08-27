<?php

namespace App\Http\Controllers;

use App\Domain\Support\Services\SupportAttachmentService;
use App\Domain\Support\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportAttachmentController extends Controller
{
    public function customer(Request $request, string $number, SupportAttachmentService $attachments, TicketService $tickets): RedirectResponse
    {
        $ticket = DB::table('tickets')->where('number', $number)->where('user_id', $request->user()->id)->first();
        abort_unless($ticket && $ticket->status !== 'resolved', 404);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:5000'], 'attachments' => ['required', 'array', 'min:1', 'max:5'], 'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']]);
        $tickets->reply($ticket->id, $request->user()->id, $data['body'] ?: 'پیوست فایل', false, $attachments->store($request->file('attachments', [])));

        return back()->with('success', 'پیوست به تیکت اضافه شد.');
    }

    public function admin(Request $request, int $ticket, SupportAttachmentService $attachments, TicketService $tickets): RedirectResponse
    {
        abort_unless(DB::table('tickets')->where('id', $ticket)->exists(), 404);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:5000'], 'is_internal' => ['nullable', 'boolean'], 'attachments' => ['required', 'array', 'min:1', 'max:5'], 'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']]);
        $tickets->reply($ticket, $request->user()->id, $data['body'] ?: 'پیوست فایل', $data['is_internal'] ?? false, $attachments->store($request->file('attachments', [])));

        return back()->with('success', 'پیوست ثبت شد.');
    }

    public function download(Request $request, int $message, int $index): StreamedResponse
    {
        $row = DB::table('ticket_messages')->join('tickets', 'tickets.id', '=', 'ticket_messages.ticket_id')->where('ticket_messages.id', $message)->select('ticket_messages.*', 'tickets.user_id as ticket_user_id')->first();
        abort_unless($row, 404);
        $isStaff = $request->user()->hasPermission('customers.view');
        abort_unless($isStaff || ((int) $row->ticket_user_id === (int) $request->user()->id && ! $row->is_internal), 403);
        $files = json_decode($row->attachments ?: '[]', true) ?: [];
        $file = $files[$index] ?? null;
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->download($file['path'], $file['name'] ?? 'attachment');
    }
}

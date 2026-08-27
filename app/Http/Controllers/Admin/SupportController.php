<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupportController extends Controller
{
    /** Lists support/part-finder tickets with SLA and status filters. */
    public function index(Request $request): View
    {
        $tickets = DB::table('tickets')->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))->when($request->filled('department'), fn ($query) => $query->where('department', $request->department))->latest()->paginate(30)->withQueryString();
        return view('admin.support.index', compact('tickets'));
    }

    /** Shows the full customer/staff ticket conversation timeline. */
    public function show(int $ticket): View
    {
        $ticketRow = DB::table('tickets')->find($ticket);
        abort_unless($ticketRow, 404);
        $messages = DB::table('ticket_messages')->leftJoin('users', 'users.id', '=', 'ticket_messages.user_id')->where('ticket_id', $ticket)->select('ticket_messages.*', 'users.name as user_name')->orderBy('ticket_messages.id')->get();
        return view('admin.support.show', ['ticket' => $ticketRow, 'messages' => $messages]);
    }

    /** Appends a staff response and optionally marks it internal-only. */
    public function reply(Request $request, int $ticket, TicketService $service): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'is_internal' => ['nullable', 'boolean']]);
        $service->reply($ticket, $request->user()->id, $data['body'], $data['is_internal'] ?? false);
        return back()->with('success', 'پاسخ ثبت شد.');
    }

    /** Closes a resolved ticket while preserving its immutable message history. */
    public function resolve(int $ticket): RedirectResponse
    {
        DB::table('tickets')->where('id', $ticket)->update(['status' => 'resolved', 'resolved_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'تیکت حل‌شده علامت‌گذاری شد.');
    }
}

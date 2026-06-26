<?php

namespace App\Http\Controllers;

use App\Models\Character;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CharacterController extends Controller
{
    /**
     * Dashboard Utama: grid kartu karakter milik user yang login.
     * Search instan ditangani di sisi frontend (lihat Dashboard.vue)
     * dengan filter di-memory karena jumlah karakter per user
     * biasanya kecil; tidak perlu round-trip ke server tiap ketik.
     */
    public function index(): Response
    {
        $characters = Character::where('user_id', Auth::id())
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'characters' => $characters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Characters/Form', [
            'character' => null,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCharacter($request);

        if ($request->hasFile('avatar')) {
            $validated['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        unset($validated['avatar']);

        $character = Auth::user()->characters()->create($validated);

        return redirect()->route('dashboard')->with('success', "Karakter \"{$character->name}\" berhasil dibuat.");
    }

    public function edit(Character $character): Response
    {
        $this->authorizeOwnership($character);

        return Inertia::render('Characters/Form', [
            'character' => $character,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, Character $character): RedirectResponse
    {
        $this->authorizeOwnership($character);

        $validated = $this->validateCharacter($request, $character->id);

        if ($request->hasFile('avatar')) {
            // Hapus avatar lama dari storage sebelum menyimpan yang baru
            if ($character->avatar_path) {
                Storage::disk('public')->delete($character->avatar_path);
            }

            $validated['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        unset($validated['avatar']);

        $character->update($validated);

        return redirect()->route('dashboard')->with('success', "Karakter \"{$character->name}\" berhasil diperbarui.");
    }

    /**
     * Hapus karakter beserta file avatar dari storage dan seluruh
     * riwayat chat-nya (cascade delete sudah diatur di migrasi
     * lewat onDelete('cascade') untuk tabel chats & messages).
     */
    public function destroy(Character $character): RedirectResponse
    {
        $this->authorizeOwnership($character);

        if ($character->avatar_path) {
            Storage::disk('public')->delete($character->avatar_path);
        }

        $character->delete();

        return redirect()->route('dashboard')->with('success', "Karakter \"{$character->name}\" telah dihapus.");
    }

    protected function validateCharacter(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'greeting' => ['required', 'string'],
            'avatar' => ['nullable', 'image', 'max:4096'], // max 4MB
            'short_description' => ['required', 'string', 'max:500'],
            'long_description' => ['nullable', 'string'],
            'example_dialogues' => ['nullable', 'array'],
            'example_dialogues.*.user' => ['nullable', 'string'],
            'example_dialogues.*.ai' => ['nullable', 'string'],
        ]);
    }

    protected function authorizeOwnership(Character $character): void
    {
        if ($character->user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki akses ke karakter ini.');
        }
    }
}

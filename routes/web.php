<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AccountSetupController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', [ProfileController::class, 'locale'])->name('locale');

/* ---------- Visitantes: instalação, login em etapas, convite, recuperação ---------- */
Route::middleware('throttle:auth-steps')->group(function () {
    Route::get('/setup', [AccountSetupController::class, 'showSetup'])->name('setup');
    Route::post('/setup', [AccountSetupController::class, 'setup']);

    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/login/phrase', [LoginController::class, 'showPhrase'])->name('login.phrase');
    Route::post('/login/phrase', [LoginController::class, 'phrase']);
    Route::get('/login/2fa', [LoginController::class, 'showTwoFactor'])->name('login.2fa');
    Route::post('/login/2fa', [LoginController::class, 'twoFactor']);
    Route::get('/login/2fa/setup', [LoginController::class, 'showTwoFactorSetup'])->name('login.2fa.setup');
    Route::post('/login/2fa/setup', [LoginController::class, 'twoFactorSetup']);
    Route::get('/login/codes', [LoginController::class, 'showCodes'])->name('login.codes');
    Route::post('/login/codes', [LoginController::class, 'codesContinue'])->name('login.codes.continue');
    Route::get('/login/passkey', [LoginController::class, 'showPasskey'])->name('login.passkey');
    Route::post('/login/passkey/options', [LoginController::class, 'passkeyOptions'])->name('login.passkey.options');
    Route::post('/login/passkey/verify', [LoginController::class, 'passkeyVerify'])->name('login.passkey.verify');

    Route::get('/invite/{token}', [AccountSetupController::class, 'showInvite'])->name('invite.show');
    Route::post('/invite/{token}', [AccountSetupController::class, 'acceptInvite']);
    Route::get('/recovery-key', [AccountSetupController::class, 'showRecoveryKey'])->name('recovery-key.show');

    Route::get('/recover', [AccountSetupController::class, 'showRecover'])->name('recover');
    Route::post('/recover', [AccountSetupController::class, 'recover']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/* ---------- Área autenticada (cofre destravado) ---------- */
Route::middleware(['auth', 'vault'])->group(function () {
    Route::get('/', [ItemController::class, 'index'])->name('dashboard');
    Route::post('/ping', fn () => response()->json(['ok' => true]))->name('ping');

    Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
    Route::post('/items', [ItemController::class, 'store'])->name('items.store');
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    Route::delete('/items/{id}/locked', [ItemController::class, 'destroyLocked'])->name('items.destroy-locked');
    Route::post('/items/{item}/reveal', [ItemController::class, 'reveal'])->middleware('throttle:secrets')->name('items.reveal');
    Route::post('/items/{item}/snooze', [ItemController::class, 'snooze'])->name('items.snooze');

    Route::post('/items/{item}/shares', [ItemController::class, 'share'])->name('items.share');
    Route::patch('/items/{item}/shares/{share}', [ItemController::class, 'updateShare'])->name('items.share.update');
    Route::delete('/items/{item}/shares/{share}', [ItemController::class, 'revoke'])->name('items.share.revoke');

    Route::post('/items/{item}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->middleware('throttle:secrets')->name('attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('/trash', [ItemController::class, 'trash'])->name('trash');
    Route::post('/trash/{id}/restore', [ItemController::class, 'restore'])->name('trash.restore');
    Route::delete('/trash/{id}', [ItemController::class, 'purge'])->name('trash.purge');

    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::put('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    Route::post('/groups/{group}/members', [GroupController::class, 'addMember'])->name('groups.members.add');
    Route::patch('/groups/{group}/members/{member}', [GroupController::class, 'updateMember'])->name('groups.members.update');
    Route::delete('/groups/{group}/members/{member}', [GroupController::class, 'removeMember'])->name('groups.members.remove');

    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'updatePreferences'])->name('profile.update');
    Route::post('/profile/theme', [ProfileController::class, 'theme'])->name('profile.theme');
    Route::put('/profile/credentials', [ProfileController::class, 'changeCredentials'])->name('profile.credentials');
    Route::post('/profile/2fa/codes', [ProfileController::class, 'regenerateCodes'])->name('profile.codes');
    Route::post('/profile/recovery-key', [ProfileController::class, 'regenerateRecoveryKey'])->name('profile.recovery-key');
    Route::post('/profile/passkeys/options', [ProfileController::class, 'passkeyOptions'])->name('passkeys.options');
    Route::post('/profile/passkeys', [ProfileController::class, 'passkeyStore'])->name('passkeys.store');
    Route::delete('/profile/passkeys/{passkey}', [ProfileController::class, 'passkeyDestroy'])->name('passkeys.destroy');
    Route::put('/profile/passkeys/requirement', [ProfileController::class, 'passkeyRequirement'])->name('passkeys.requirement');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/audit', [UserController::class, 'audit'])->name('audit');
    });
});

@extends('layouts.builder360-classic')

@section('title', 'My Profile — Builder360')

@section('content')
@php($currentTheme = session('builder360.theme', 'light'))
<section class="b360-profile-page" aria-labelledby="profile-title">
    <header class="b360-profile-hero">
        <div class="b360-profile-avatar-wrap">
            <x-ui.user-avatar
                :user="auth()->user()"
                :label="$page->name"
                class="b360-avatar b360-profile-avatar"
            />
        </div>
        <div class="b360-profile-identity">
            <span class="b360-profile-eyebrow">My Profile</span>
            <h1 id="profile-title">{{ $page->name }}</h1>
            <p>{{ $page->email }}</p>
            <div class="b360-profile-badges" aria-label="Current account context">
                <span><i class="fa-solid fa-user-shield" aria-hidden="true"></i>{{ $page->activeRole }}</span>
                <span><i class="fa-solid fa-building" aria-hidden="true"></i>{{ $page->companyName }}</span>
                <span class="is-success"><i class="fa-solid fa-circle" aria-hidden="true"></i>{{ $page->status }}</span>
            </div>
        </div>
        <div class="b360-profile-actions">
            <a class="blade-action" href="{{ route('builder360.dashboard') }}"><i class="fa-solid fa-gauge" aria-hidden="true"></i>Dashboard</a>
            <a class="blade-action" href="{{ route('notifications.index') }}"><i class="fa-regular fa-bell" aria-hidden="true"></i>Notifications</a>
        </div>
    </header>

    <div class="b360-profile-layout">
        <div class="b360-profile-primary">
            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-solid fa-camera" aria-hidden="true"></i></span>
                    <div><span>Appearance</span><h2>Profile photo</h2></div>
                </div>
                <form
                    method="POST"
                    action="{{ route('builder360.profile-photo.update') }}"
                    enctype="multipart/form-data"
                    class="b360-profile-photo-form"
                    x-data="profilePhotoPicker"
                    x-on:submit="submitPhoto"
                >
                    @csrf
                    @method('PATCH')
                    <div class="b360-profile-photo-preview" aria-hidden="true">
                        <x-ui.user-avatar :user="auth()->user()" :label="$page->name" class="b360-avatar" />
                        <img x-show="previewUrl" x-bind:src="previewUrl" alt="">
                    </div>
                    <div class="b360-profile-photo-copy">
                        <strong>Choose a new profile photo</strong>
                        <p>JPG, PNG, or WebP. Maximum file size 5 MB.</p>
                        <span x-show="selectedName" x-text="selectedName" class="b360-profile-file-name"></span>
                        <span x-show="error" x-text="error" class="b360-field-error" role="alert"></span>
                        @error('photo')<span class="b360-field-error" role="alert">{{ $message }}</span>@enderror
                    </div>
                    <div class="b360-profile-photo-actions">
                        <label class="blade-action" for="profile-photo"><i class="fa-regular fa-image" aria-hidden="true"></i>Choose photo</label>
                        <input id="profile-photo" class="visually-hidden" type="file" name="photo" accept="image/jpeg,image/png,image/webp" x-on:change="choosePhoto" required>
                        <button class="blade-primary-action" type="submit" x-bind:disabled="busy">
                            <span x-show="! busy">Save photo</span><span x-show="busy">Saving…</span>
                        </button>
                    </div>
                </form>
            </article>

            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-regular fa-id-card" aria-hidden="true"></i></span>
                    <div><span>Overview</span><h2>Account details</h2></div>
                    <small>Managed by administrator</small>
                </div>
                <dl class="b360-profile-details">
                    <div><dt>Name</dt><dd>{{ $page->name }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $page->email }}</dd></div>
                    <div><dt>Employee</dt><dd>{{ $page->employeeCode }}</dd></div>
                    <div><dt>Company</dt><dd>{{ $page->companyCode }} · {{ $page->companyName }}</dd></div>
                </dl>
            </article>

            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
                    <div><span>Recent Activity</span><h2>Account activity</h2></div>
                    <small>{{ count($page->recentActivity) }} shown</small>
                </div>
                <div class="b360-profile-activity">
                    @forelse($page->recentActivity as $activity)
                        <div class="b360-profile-activity-row">
                            <span class="b360-profile-activity-dot" aria-hidden="true"></span>
                            <div><strong>{{ $activity->action }}</strong><span>{{ $activity->event }}</span></div>
                            <time>{{ $activity->occurredAt }}</time>
                        </div>
                    @empty
                        <div class="b360-profile-empty">
                            <i class="fa-regular fa-clock" aria-hidden="true"></i>
                            <strong>No recent activity</strong>
                            <span>Your account activity will appear here.</span>
                        </div>
                    @endforelse
                </div>
            </article>
        </div>

        <aside class="b360-profile-secondary" aria-label="Profile settings and access">
            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></span>
                    <div><span>Account Access</span><h2>Current access</h2></div>
                </div>
                <p class="b360-profile-admin-note"><i class="fa-solid fa-lock" aria-hidden="true"></i>Roles, permissions, and project access are managed by your administrator.</p>
                <dl class="b360-profile-details">
                    <div><dt>Assigned role</dt><dd>{{ $page->assignedRole }}</dd></div>
                    <div><dt>Role preview</dt><dd>{{ $page->activeRole }}</dd></div>
                    <div><dt>Access level</dt><dd>{{ $page->accessLevel }}</dd></div>
                    <div><dt>Access rules</dt><dd>{{ number_format($page->permissionCount) }} assigned</dd></div>
                    <div><dt>Project view</dt><dd>{{ $page->projectContext }}</dd></div>
                </dl>
            </article>

            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-solid fa-palette" aria-hidden="true"></i></span>
                    <div><span>Preferences</span><h2>Theme</h2></div>
                </div>
                <div class="b360-profile-preference">
                    <div><strong>{{ ucfirst($currentTheme) }} appearance</strong><span>Applied across Builder360 for this session.</span></div>
                    <form method="POST" action="{{ route('builder360.theme.store') }}" x-on:submit="changeTheme">
                        @csrf
                        <input type="hidden" name="theme" value="{{ $currentTheme === 'dark' ? 'light' : 'dark' }}">
                        <button class="blade-action" type="submit" aria-label="Switch to {{ $currentTheme === 'dark' ? 'light' : 'dark' }} theme" x-bind:aria-label="themeLabel" x-bind:disabled="themeBusy">
                            <i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i>Switch theme
                        </button>
                        <span class="b360-form-error" aria-live="polite" x-text="themeError"></span>
                    </form>
                </div>
            </article>

            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
                    <div><span>Security</span><h2>Login protection</h2></div>
                </div>
                <dl class="b360-profile-details">
                    <div><dt>Account status</dt><dd><span class="b360-profile-status is-success">{{ $page->status }}</span></dd></div>
                    <div><dt>Email verification</dt><dd>{{ $page->emailVerified ? 'Verified' : 'Pending verification' }}</dd></div>
                    <div><dt>Authenticated session</dt><dd>Active</dd></div>
                    <div><dt>Password recovery</dt><dd>Available from login</dd></div>
                </dl>
                <form class="b360-profile-logout" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="blade-danger-action"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>Logout from this session</button>
                </form>
            </article>
        </aside>
    </div>
</section>
@endsection

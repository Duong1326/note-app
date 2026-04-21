@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    <div class="fn-dashboard">

        {{-- Header --}}
        <div class="fn-profile-header">
            <h1>Profile Settings</h1>
            <p>Manage your personal information, preferences, and security settings to tailor your LiveNote experience.</p>
        </div>

        {{-- Bento Grid --}}
        <div class="row g-4">

            {{-- Left Column --}}
            <div class="col-12 col-lg-4 d-flex flex-column gap-4">

                {{-- Avatar Card --}}
                <div class="fn-profile-card text-center">
                    <div class="fn-avatar-wrapper">
                        <div class="fn-avatar-img d-flex align-items-center justify-content-center"
                            style="background: var(--fn-primary-container); color: var(--fn-on-primary); font-size: 2.5rem; font-weight: 800;">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <button type="button" class="fn-avatar-edit-btn" title="Change avatar">
                            <span class="material-symbols-outlined">photo_camera</span>
                        </button>
                    </div>
                    <h2 class="fn-profile-name">{{ Auth::user()->name }}</h2>
                    <p class="fn-profile-email">{{ Auth::user()->email }}</p>
                    <span class="fn-profile-badge">
                        <span class="fn-profile-badge-dot"></span>
                        Member
                    </span>
                </div>
            </div>

            {{-- Right Column --}}
            <div class="col-12 col-lg-8 d-flex flex-column gap-4">

                {{-- Personal Info --}}
                <div class="fn-profile-card">
                    <h3 class="fn-section-heading">
                        <span class="material-symbols-outlined">badge</span>
                        Personal Information
                    </h3>

                    <form method="POST" action="{{ route('profile.update') }}" id="profileForm">
                        @csrf
                        @method('PUT')

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="fn-form-label" for="profileName">Display Name</label>
                                <input type="text" name="name" id="profileName" class="fn-form-input"
                                    value="{{ Auth::user()->name }}">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="fn-form-label" for="profileEmail">Email Address</label>
                                <input type="email" id="profileEmail" class="fn-form-input"
                                    value="{{ Auth::user()->email }}" readonly>
                                <p class="fn-form-hint">
                                    <span class="material-symbols-outlined">info</span>
                                    Contact support to change email.
                                </p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="fn-form-label" for="profileBio">Bio</label>
                            <textarea name="bio" id="profileBio" class="fn-form-textarea" rows="4"
                                placeholder="Tell us a little about yourself...">{{ Auth::user()->bio ?? '' }}</textarea>
                        </div>
                    </form>
                </div>

                {{-- Security --}}
                <div class="fn-profile-card">
                    <h3 class="fn-section-heading">
                        <span class="material-symbols-outlined">security</span>
                        Security
                    </h3>

                    <div class="d-flex flex-column gap-4">
                        {{-- Password --}}
                        <div class="fn-security-row">
                            <div>
                                <h4 class="fn-security-label">Password</h4>
                                <p class="fn-security-desc">Keep your account secure with a strong password.</p>
                            </div>
                            <a href="{{ route('password.request') }}" class="fn-btn-outline">Change Password</a>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="d-flex justify-content-end gap-3 mt-2">
                    <a href="{{ route('dashboard') }}" class="fn-btn-ghost">Cancel</a>
                    <button type="submit" form="profileForm" class="fn-btn-primary">Save Changes</button>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}">
@endpush
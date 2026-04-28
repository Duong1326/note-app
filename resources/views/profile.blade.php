@extends('layouts.app')

@section('title', 'Hồ sơ')

@section('content')
    <div class="fn-dashboard">

        {{-- Header --}}
        <div class="fn-profile-header">
            <h1>Cài đặt hồ sơ</h1>
            <p>Quản lý thông tin cá nhân và cài đặt bảo mật để tùy chỉnh trải nghiệm LiveNote của bạn.</p>
        </div>

        {{-- Bento Grid --}}
        <div class="row g-4">
            {{-- Left Column --}}
            <div class="col-12 col-lg-4 d-flex flex-column gap-4">
                {{-- Avatar Card --}}
                <div class="fn-profile-card text-center">
                    <div class="fn-avatar-wrapper">
                        @if(Auth::user()->avatarUrl())
                            <img src="{{ Auth::user()->avatarUrl() }}" alt="Avatar" class="fn-avatar-img" id="profileAvatarImg">
                        @else
                            <div class="fn-avatar-img d-flex align-items-center justify-content-center"
                                id="profileAvatarInitial"
                                style="background: var(--fn-primary-container); color: var(--fn-on-primary); font-size: 2.5rem; font-weight: 800;">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                        @endif
                        <button type="button" class="fn-avatar-edit-btn" title="Thay đổi ảnh đại diện"
                            onclick="document.getElementById('avatarFileInput').click()">
                            <span class="material-symbols-outlined">photo_camera</span>
                        </button>
                        <input type="file" id="avatarFileInput" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                            class="d-none" data-upload-url="{{ route('profile.avatar') }}">
                    </div>
                    <h2 class="fn-profile-name">{{ Auth::user()->name }}</h2>
                    <p class="fn-profile-email">{{ Auth::user()->email }}</p>
                    <span class="fn-profile-badge">
                        <span class="fn-profile-badge-dot"></span>
                        Thành viên
                    </span>
                </div>
            </div>

            {{-- Right Column --}}
            <div class="col-12 col-lg-8 d-flex flex-column gap-4">

                {{-- Personal Info --}}
                <div class="fn-profile-card">
                    <h3 class="fn-section-heading">
                        <span class="material-symbols-outlined">badge</span>
                        Thông tin cá nhân
                    </h3>

                    <form method="POST" action="{{ route('profile.update') }}" id="profileForm">
                        @csrf
                        @method('PUT')

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="fn-form-label" for="profileName">Tên hiển thị</label>
                                <input type="text" name="name" id="profileName" class="fn-form-input"
                                    value="{{ Auth::user()->name }}">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="fn-form-label" for="profileEmail">Địa chỉ Email</label>
                                <input type="email" id="profileEmail" class="fn-form-input"
                                    value="{{ Auth::user()->email }}" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="fn-form-label" for="profileBio">Giới thiệu</label>
                            <textarea name="bio" id="profileBio" class="fn-form-textarea" rows="4"
                                placeholder="Hãy viết đôi điều về bản thân bạn...">{{ Auth::user()->bio ?? '' }}</textarea>
                        </div>
                    </form>
                </div>

                {{-- Security --}}
                <div class="fn-profile-card">
                    <h3 class="fn-section-heading">
                        <span class="material-symbols-outlined">security</span>
                        Bảo mật
                    </h3>

                    <div class="d-flex flex-column gap-4">
                        {{-- Password --}}
                        <div class="fn-security-row">
                            <div>
                                <h4 class="fn-security-label">Mật khẩu</h4>
                                <p class="fn-security-desc">Giữ tài khoản của bạn an toàn với mật khẩu mạnh.</p>
                            </div>
                            <a href="{{ route('password.request') }}" class="fn-btn-outline">Đổi mật khẩu</a>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="d-flex justify-content-end gap-3 mt-2">
                    <button type="submit" id="btnSaveProfile" form="profileForm" class="fn-btn-primary">Lưu thay
                        đổi</button>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/profile.js') }}"></script>
@endpush
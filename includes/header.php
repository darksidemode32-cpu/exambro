<?php
/**
 * Admin Layout Header & Sidebar Component
 * Native PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/token_generator.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$adminName = $_SESSION['admin_name'] ?? 'Administrator';
$adminRole = $_SESSION['admin_role'] ?? 'Superadmin';
$tokenInfo = getCurrentExitTokenInfo();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - Exambro Proctor' : 'Exambro Master Server & Admin' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="icon" type="image/svg+xml" href="assets/img/default-logo.svg">
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="assets/img/default-logo.svg" alt="Exambro Logo" class="sidebar-logo">
            <div class="sidebar-brand">
                <h1>EXAMBRO</h1>
                <span>Proctor Server v2.4</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Menu Utama</div>
            <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Dashboard Live
            </a>
            <a href="schools.php" class="nav-link <?= $currentPage === 'schools.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                Sekolah &amp; Multi-Tenant
            </a>
            <a href="students.php" class="nav-link <?= $currentPage === 'students.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Monitoring Siswa &amp; GPS
            </a>

            <div class="nav-label">Keamanan &amp; Audit</div>
            <a href="security_logs.php" class="nav-link <?= $currentPage === 'security_logs.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                Log Keamanan &amp; IP Block
            </a>
            <a href="clear_logs.php" class="nav-link <?= $currentPage === 'clear_logs.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                Pembersihan Log
            </a>
            <a href="settings.php" class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Pengaturan Sistem
            </a>

            <div class="nav-label">Pengujian &amp; Klien</div>
            <a href="student/index.php" target="_blank" class="nav-link" style="color: var(--primary);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                Buka Simulator Siswa &#x2197;
            </a>
            <a href="exam_demo.php" target="_blank" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Demo Ujian CBT &#x2197;
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
                <div class="user-meta">
                    <h4><?= e($adminName) ?></h4>
                    <span><?= e($adminRole) ?></span>
                </div>
            </div>
            <a href="logout.php" title="Logout" style="color: var(--danger); text-decoration: none; display: flex; align-items: center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <h2 class="topbar-title"><?= isset($pageHeading) ? e($pageHeading) : 'Dashboard Pengawasan' ?></h2>
                <span class="server-pill">
                    <span class="pulse-dot"></span>
                    PHP <?= PHP_VERSION ?> &bull; Master Online
                </span>
            </div>

            <div class="topbar-right">
                <!-- Topbar Active Token Pill -->
                <div class="quick-token-badge" title="Password Keluar Aktif (Berubah tiap 5 menit)">
                    <span class="quick-token-label">Token 5-Mnt:</span>
                    <span class="quick-token-val" id="quickTokenVal"><?= e($tokenInfo['token']) ?></span>
                </div>

                <a href="student/index.php" target="_blank" class="btn btn-secondary btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                    Uji Siswa
                </a>
            </div>
        </header>

        <!-- Body Content -->
        <main class="content-body">
            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

<?php
include 'menu.php';
include 'conn.php';

// permisos / sesión
$tipoUsuario = $_SESSION['tus'] ?? null;
$userid = $_SESSION['uid'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Templates</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .reportes-dashboard {
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            --emerald-50: #ecfdf5;
            --emerald-600: #059669;
            --emerald-700: #047857;
            --emerald-800: #065f46;
            --blue-50: #eff6ff;
            --blue-600: #2563eb;
            --blue-700: #1d4ed8;
            --fuchsia-50: #fdf4ff;
            --fuchsia-600: #c026d3;
            --fuchsia-700: #a21caf;
            --amber-50: #fffbeb;
            --amber-600: #d97706;
            --amber-700: #b45309;
            --sky-50: #f0f9ff;
            --sky-600: #0284c7;
            --sky-700: #0369a1;
            --green-600: #16a34a;
            --orange-400: #fb923c;
            --lime-600: #65a30d;
            --white: #ffffff;
        }

        .reportes-dashboard * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .reportes-dashboard {
            background-color: var(--slate-50);
            color: var(--slate-900);
            line-height: 1.5;
            min-height: 100vh;
        }

        .reportes-dashboard .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .reportes-dashboard .flex {
            display: flex;
        }

        .reportes-dashboard .flex-col {
            flex-direction: column;
        }

        .reportes-dashboard .items-center {
            align-items: center;
        }

        .reportes-dashboard .items-start {
            align-items: flex-start;
        }

        .reportes-dashboard .justify-between {
            justify-content: space-between;
        }

        .reportes-dashboard .gap-2 {
            gap: 0.5rem;
        }

        .reportes-dashboard .gap-3 {
            gap: 0.75rem;
        }

        .reportes-dashboard .gap-4 {
            gap: 1rem;
        }

        .reportes-dashboard .gap-5 {
            gap: 1.25rem;
        }

        .reportes-dashboard .gap-6 {
            gap: 1.5rem;
        }

        .reportes-dashboard .space-y-3>*+* {
            margin-top: 0.75rem;
        }

        .reportes-dashboard .space-y-4>*+* {
            margin-top: 1rem;
        }

        .reportes-dashboard .grid {
            display: grid;
        }

        .reportes-dashboard .grid-cols-1 {
            grid-template-columns: 1fr;
        }

        .reportes-dashboard .grid-cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .reportes-dashboard .grid-cols-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .reportes-dashboard .grid-cols-12 {
            grid-template-columns: repeat(12, 1fr);
        }

        .reportes-dashboard .rounded-2xl {
            border-radius: 1rem;
        }

        .reportes-dashboard .rounded-xl {
            border-radius: 0.75rem;
        }

        .reportes-dashboard .rounded-full {
            border-radius: 9999px;
        }

        .reportes-dashboard .h-2\.5 {
            height: 0.625rem;
        }

        .reportes-dashboard .w-2\.5 {
            width: 0.625rem;
        }

        .reportes-dashboard .min-w-0 {
            min-width: 0;
        }

        .reportes-dashboard .shrink-0 {
            flex-shrink: 0;
        }

        .reportes-dashboard .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .reportes-dashboard .p-3 {
            padding: 0.75rem;
        }

        .reportes-dashboard .p-4 {
            padding: 1rem;
        }

        .reportes-dashboard .p-5 {
            padding: 1.25rem;
        }

        .reportes-dashboard .p-6 {
            padding: 1.5rem;
        }

        .reportes-dashboard .px-4 {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .reportes-dashboard .px-5 {
            padding-left: 1.25rem;
            padding-right: 1.25rem;
        }

        .reportes-dashboard .px-2\.5 {
            padding-left: 0.625rem;
            padding-right: 0.625rem;
        }

        .reportes-dashboard .py-1 {
            padding-top: 0.25rem;
            padding-bottom: 0.25rem;
        }

        .reportes-dashboard .py-2 {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .reportes-dashboard .py-4 {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .reportes-dashboard .py-6 {
            padding-top: 1.5rem;
            padding-bottom: 1.5rem;
        }

        .reportes-dashboard .mt-1 {
            margin-top: 0.25rem;
        }

        .reportes-dashboard .mt-2 {
            margin-top: 0.5rem;
        }

        .reportes-dashboard .mt-3 {
            margin-top: 0.75rem;
        }

        .reportes-dashboard .mt-4 {
            margin-top: 1rem;
        }

        .reportes-dashboard .mt-6 {
            margin-top: 1.5rem;
        }

        .reportes-dashboard .mt-0\.5 {
            margin-top: 0.125rem;
        }

        .reportes-dashboard .mb-2 {
            margin-bottom: 0.5rem;
        }

        .reportes-dashboard .text-xs {
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .reportes-dashboard .text-sm {
            font-size: 0.875rem;
            line-height: 1.25rem;
        }

        .reportes-dashboard .text-base {
            font-size: 1rem;
            line-height: 1.5rem;
        }

        .reportes-dashboard .text-lg {
            font-size: 1.125rem;
            line-height: 1.75rem;
        }

        .reportes-dashboard .text-2xl {
            font-size: 1.5rem;
            line-height: 2rem;
        }

        .reportes-dashboard .font-semibold {
            font-weight: 600;
        }

        .reportes-dashboard .font-bold {
            font-weight: 700;
        }

        .reportes-dashboard .text-right {
            text-align: right;
        }

        .reportes-dashboard .uppercase {
            text-transform: uppercase;
        }

        .reportes-dashboard .tracking-wide {
            letter-spacing: 0.025em;
        }

        .reportes-dashboard .tracking-tight {
            letter-spacing: -0.025em;
        }

        .reportes-dashboard .text-slate-500 {
            color: var(--slate-500);
        }

        .reportes-dashboard .text-slate-600 {
            color: var(--slate-600);
        }

        .reportes-dashboard .text-slate-700 {
            color: var(--slate-700);
        }

        .reportes-dashboard .text-slate-900 {
            color: var(--slate-900);
        }

        .reportes-dashboard .text-emerald-700 {
            color: var(--emerald-700);
        }

        .reportes-dashboard .text-blue-700 {
            color: var(--blue-700);
        }

        .reportes-dashboard .text-fuchsia-700 {
            color: var(--fuchsia-700);
        }

        .reportes-dashboard .text-amber-700 {
            color: var(--amber-700);
        }

        .reportes-dashboard .text-sky-700 {
            color: var(--sky-700);
        }

        .reportes-dashboard .bg-white {
            background-color: var(--white);
        }

        .reportes-dashboard .bg-slate-50 {
            background-color: var(--slate-50);
        }

        .reportes-dashboard .bg-blue-50 {
            background-color: var(--blue-50);
        }

        .reportes-dashboard .bg-emerald-50 {
            background-color: var(--emerald-50);
        }

        .reportes-dashboard .bg-amber-50 {
            background-color: var(--amber-50);
        }

        .reportes-dashboard .bg-fuchsia-50 {
            background-color: var(--fuchsia-50);
        }

        .reportes-dashboard .bg-sky-50 {
            background-color: var(--sky-50);
        }

        .reportes-dashboard .bg-gradient-emerald {
            background: linear-gradient(to right, var(--emerald-600), var(--sky-600));
        }

        .reportes-dashboard .bg-gradient-blue {
            background: linear-gradient(to right, var(--blue-600), var(--sky-600));
        }

        .reportes-dashboard .bg-gradient-sky {
            background: linear-gradient(to right, var(--sky-700), var(--blue-600));
        }

        .reportes-dashboard .bg-gradient-fuchsia {
            background: linear-gradient(to right, var(--fuchsia-600), var(--orange-400));
        }

        .reportes-dashboard .bg-gradient-green {
            background: linear-gradient(to right, var(--emerald-700), var(--lime-600));
        }

        .reportes-dashboard .shadow-sm {
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .ring-1 {
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .ring-black\/5 {
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .ring-black\/10 {
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
        }

        .reportes-dashboard .border-b {
            border-bottom: 1px solid var(--slate-200);
        }

        .reportes-dashboard .overflow-x-auto {
            overflow-x: auto;
        }

        .reportes-dashboard .custom-scroll {
            scrollbar-width: thin;
            scrollbar-color: var(--slate-300) transparent;
        }

        .reportes-dashboard .custom-scroll::-webkit-scrollbar {
            height: 6px;
        }

        .reportes-dashboard .custom-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .reportes-dashboard .custom-scroll::-webkit-scrollbar-thumb {
            background-color: var(--slate-300);
            border-radius: 20px;
        }

        .reportes-dashboard .dashboard-header {
            position: sticky;
            top: 0;
            z-index: 30;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(8px);
        }

        .reportes-dashboard .card {
            border-radius: 1rem;
            background-color: var(--white);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .stat-card {
            border-radius: 1rem;
            padding: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .platform-card {
            min-width: 260px;
            max-width: 320px;
            flex-shrink: 0;
        }

        .reportes-dashboard .platform-card-top {
            height: 0.375rem;
            width: 100%;
            border-radius: 1rem 1rem 0 0;
        }

        .reportes-dashboard .progress-bar {
            height: 0.5rem;
            width: 100%;
            border-radius: 9999px;
            background-color: var(--slate-100);
            overflow: hidden;
        }

        .reportes-dashboard .progress-bar-fill {
            height: 100%;
            border-radius: 9999px;
        }

        .reportes-dashboard .rd-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 9999px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background-color: #8d774a;
            color: var(--white);
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
        }

        .reportes-dashboard .rd-btn i,
        .reportes-dashboard .btn i,
        .reportes-dashboard .btn-sm i,
        .reportes-dashboard table.table .btn i,
        .reportes-dashboard table.table .btn-sm i,
        .reportes-dashboard table.table button i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .reportes-dashboard .rd-btn:hover {
            background-color: #7b6840;
        }

        .reportes-dashboard .rd-btn-primary {
            background-color: #464646;
            color: var(--white);
        }

        .reportes-dashboard .rd-btn-primary:hover {
            background-color: #2f2f2f;
        }

        .reportes-dashboard .select-wrapper {
            position: relative;
        }

        .reportes-dashboard select.custom-select,
        .reportes-dashboard input.custom-input {
            appearance: none;
            border-radius: 9999px;
            background-color: var(--slate-50);
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--slate-900);
            border: 1px solid rgba(0, 0, 0, 0.1);
            width: 100%;
        }

        .reportes-dashboard .select-arrow {
            pointer-events: none;
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--slate-500);
        }

        .reportes-dashboard .search-input {
            width: 16rem;
            border-radius: 9999px;
            background-color: var(--slate-50);
            padding: 0.5rem 0.75rem 0.5rem 2.5rem;
            font-size: 0.875rem;
            color: var(--slate-900);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .reportes-dashboard .search-icon {
            pointer-events: none;
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--slate-400);
        }

        .reportes-dashboard .table-header {
            background-color: var(--slate-50);
            padding: 0.5rem 1rem;
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--slate-600);
        }

        .reportes-dashboard .table-row {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }

        .reportes-dashboard .tabular-nums {
            font-variant-numeric: tabular-nums;
        }

        .reportes-dashboard .space-y-3>*+* {
            margin-top: 0.75rem;
        }

        .reportes-dashboard .divide-y>*+* {
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .keyword-card {
            border-radius: 1rem;
            background-color: var(--slate-50);
            padding: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .star-card {
            border-radius: 1rem;
            background-color: var(--slate-50);
            padding: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .chart-container {
            height: 20rem;
            width: 100%;
        }

        .reportes-dashboard .btn,
        .reportes-dashboard .btn-sm {
            border-radius: 9999px;
            font-weight: 600;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
        }

        .reportes-dashboard .btn-primary {
            background-color: var(--slate-900);
            border-color: var(--slate-900);
            color: var(--white);
        }

        .reportes-dashboard .btn-primary:hover {
            background-color: var(--slate-800);
            border-color: var(--slate-800);
            color: var(--white);
        }

        .reportes-dashboard .btn-secondary {
            background-color: var(--slate-100);
            border-color: var(--slate-200);
            color: var(--slate-900);
        }

        .reportes-dashboard .btn-secondary:hover {
            background-color: var(--slate-200);
            border-color: var(--slate-300);
        }

        .reportes-dashboard .btn-success {
            background-color: var(--emerald-600);
            border-color: var(--emerald-600);
            color: var(--white);
        }

        .reportes-dashboard .btn-success:hover {
            background-color: var(--emerald-700);
            border-color: var(--emerald-700);
            color: var(--white);
        }

        .reportes-dashboard .btn-danger {
            background-color: #dc2626;
            border-color: #dc2626;
            color: var(--white);
        }

        .reportes-dashboard .btn-warning {
            background-color: var(--amber-600);
            border-color: var(--amber-600);
            color: var(--white);
        }

        .reportes-dashboard .btn-info {
            background-color: var(--sky-600);
            border-color: var(--sky-600);
            color: var(--white);
        }

        .reportes-dashboard .btn-outline {
            background-color: transparent;
            border-color: var(--slate-300);
            color: var(--slate-900);
        }

        .reportes-dashboard .btn-outline:hover {
            background-color: var(--slate-100);
        }

        .reportes-dashboard table.table .btn,
        .reportes-dashboard table.table .btn-sm,
        .reportes-dashboard table.table button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 9999px;
            padding: 0.4rem 0.9rem;
            font-weight: 600;
            font-size: 0.8125rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background-color: var(--slate-50);
            color: var(--slate-900);
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
        }

        .reportes-dashboard table.table .btn:hover,
        .reportes-dashboard table.table .btn-sm:hover,
        .reportes-dashboard table.table button:hover {
            background-color: var(--slate-100);
        }

        .reportes-dashboard table.table .btn:disabled,
        .reportes-dashboard table.table .btn-sm:disabled,
        .reportes-dashboard table.table button:disabled {
            background-color: var(--slate-100);
            border-color: var(--slate-200);
            color: var(--slate-500);
            cursor: not-allowed;
        }

        .reportes-dashboard table.table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
            font-size: 0.875rem;
            color: var(--slate-900);
        }

        .reportes-dashboard table.table thead th {
            background-color: var(--slate-50);
            color: var(--slate-600);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--slate-200);
            padding: 0.75rem 1rem;
            white-space: nowrap;
        }

        .reportes-dashboard table.table tbody td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--slate-100);
            vertical-align: middle;
        }

        .reportes-dashboard table.table tbody tr:hover {
            background-color: var(--slate-50);
        }

        .reportes-dashboard table.table-striped tbody tr:nth-of-type(odd) {
            background-color: #fbfdff;
        }

        .reportes-dashboard table.table-striped tbody tr:nth-of-type(odd):hover {
            background-color: var(--slate-50);
        }

        .reportes-dashboard .table-responsive,
        .reportes-dashboard .overflow-x-auto {
            border-radius: 1rem;
        }

        @media (min-width: 640px) {
            .reportes-dashboard .sm\:flex-row {
                flex-direction: row;
            }

            .reportes-dashboard .sm\:items-center {
                align-items: center;
            }

            .reportes-dashboard .sm\:justify-between {
                justify-content: space-between;
            }
        }

        @media (min-width: 768px) {
            .reportes-dashboard .md\:grid-cols-2 {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .reportes-dashboard .lg\:grid-cols-4 {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (min-width: 1280px) {
            .reportes-dashboard .xl\:grid-cols-3 {
                grid-template-columns: repeat(3, 1fr);
            }

            .reportes-dashboard .xl\:grid-cols-4 {
                grid-template-columns: repeat(4, 1fr);
            }

            .reportes-dashboard .xl\:col-span-3 {
                grid-column: span 3 / span 3;
            }

            .reportes-dashboard .xl\:col-span-1 {
                grid-column: span 1 / span 1;
            }
        }

        .template-builder-section {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 0.75rem;
            background-color: #fff;
        }
        .tb-block {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            background-color: #f8fafc;
        }
        .tb-block:last-child {
            margin-bottom: 0;
        }
        .tb-toolbar {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .tb-toolbar select,
        .tb-toolbar input[type="color"],
        .tb-toolbar input[type="text"] {
            width: 100%;
            font-size: 0.8125rem;
        }
        .tb-toolbar .tb-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.35rem;
        }
        .tb-editable {
            min-height: 90px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.65rem;
            background-color: #fff;
            white-space: pre-wrap;
            outline: none;
        }
        .tb-editable,
        .tb-editable * {
            font-family: inherit;
            font-size: inherit;
            font-weight: inherit;
            color: inherit;
            line-height: inherit;
            letter-spacing: inherit;
        }
        .tb-editable[data-placeholder]:empty::before {
            content: attr(data-placeholder);
            color: #9ca3af;
        }
        .tb-image-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .tb-image-preview-wrap {
            border: 1px dashed #cbd5e1;
            border-radius: 0.5rem;
            min-height: 80px;
            padding: 0.5rem;
            background: #fff;
        }
        .tb-image-preview-wrap img {
            max-width: 100%;
            height: auto;
            display: inline-block;
        }
        .tb-block-note {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.4rem;
        }
        .tb-spacer-box {
            border: 1px dashed #cbd5e1;
            border-radius: 0.5rem;
            background: #fff;
            padding: 0.5rem;
        }
        .tb-spacer-preview {
            border: 1px solid #e5e7eb;
            border-radius: 0.35rem;
            background: repeating-linear-gradient(
                -45deg,
                #f8fafc,
                #f8fafc 6px,
                #eef2f7 6px,
                #eef2f7 12px
            );
            width: 100%;
        }
        .modal-dialog.modal-template-builder {
            max-width: 96vw;
        }
        .modal-template-builder .modal-content {
            max-height: 90vh;
        }
        .modal-template-builder .modal-body {
            overflow-y: auto;
        }
        .template-builder-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(360px, 0.8fr);
            gap: 1rem;
            align-items: start;
        }
        .template-live-preview {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            background: #fff;
            overflow: hidden;
            position: sticky;
            top: 0;
        }
        .template-live-preview-head {
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
        }
        .template-live-preview-head .title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #0f172a;
        }
        .template-live-preview-head .sub {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.1rem;
        }
        .template-live-preview-frame {
            width: 100%;
            height: 60vh;
            border: 0;
            display: block;
            background: #fff;
        }
        @media (max-width: 900px) {
            .tb-toolbar {
                grid-template-columns: 1fr 1fr;
            }
            .tb-image-row {
                grid-template-columns: 1fr;
            }
            .template-builder-grid {
                grid-template-columns: 1fr;
            }
            .template-live-preview {
                position: relative;
            }
            .template-live-preview-frame {
                height: 50vh;
            }
        }
        .emoji-modal .emoji-title {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: .2px;
        }
        .emoji-modal .emoji-subtitle {
            font-size: .85rem;
            color: #6c757d;
        }
        .emoji-modal .emoji-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            background: #fff;
            padding: 12px 14px;
            height: 100%;
        }
        .emoji-modal .emoji-card h6 {
            font-size: .95rem;
            margin-bottom: 8px;
        }
        .emoji-modal .emoji-list {
            font-size: 1.2rem;
            line-height: 1.6;
            letter-spacing: .4px;
        }
        .emoji-modal .emoji-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            font-size: .85rem;
        }
    </style>
</head>

<body>
    <div class="reportes-dashboard">
        <div class="py-6">
            <div class="card">
                <div class="flex sm:flex-row flex-col sm:justify-between sm:items-center gap-3 px-5 py-4 border-b">
                    <div>
                        <div class="font-semibold text-slate-900 text-sm">Email Templates</div>
                        <div class="mt-1 text-slate-500 text-xs">Gestión de plantillas de correo para marketing</div>
                    </div>
                </div>
            </div>

            <div class="card mt-6">
                <div class="flex sm:flex-row flex-col sm:justify-between sm:items-center gap-3 px-5 py-4 border-b">
                    <div>
                        <div class="font-semibold text-slate-900 text-sm">Listado de plantillas</div>
                        <div class="mt-1 text-slate-500 text-xs">Plantillas disponibles y programación</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button class="rd-btn rd-btn-primary" id="btnAddTemplate">Agregar plantilla</button>
                    </div>
                </div>
                <div class="p-5">
                    <div class="overflow-x-auto custom-scroll">
                        <table id="templatesTable" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Asunto</th>
                                    <th>Título</th>
                                    <th>Creado</th>
                                    <th>Programación</th>
                                    <th>Acciones</th>
                                    <th style="width:180px; text-align:center;">Envíos</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalTemplate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-template-builder">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar plantilla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="template-builder-grid">
                        <div>
                            <form id="formAddTemplate">
                        <div class="mb-3">
                            <label class="form-label">Nombre de la plantilla (No se manda por correo, solo es para
                                identificar)</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" required>

                            <div class="mt-5 modal-vars" aria-hidden="true">
                                <div class="modal-var"><strong>Lead Name</strong> = <code>$full_name</code></div>
                                <div class="modal-var"><strong>Wedding Date</strong> = <code>$wedding_date</code></div>
                                <div class="modal-var"><strong>Schedule Button</strong> = <code>$schedule_button</code> — Inserta botón de agenda con tracking (tabla e id)</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Asunto del correo</label>
                            <input type="text" name="asunto" id="asunto" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Título del correo</label>
                            <div id="titleBlockContainer" class="template-builder-section"></div>
                            <input type="hidden" name="titulo" id="tituloHidden">
                        </div>
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="mb-0 form-label">Cuerpo del correo</label>
                                <button type="button" class="btn-primary btn btn-sm" id="btnEmojis">Ver emojis permitidos 😊</button>
                            </div>
                            <div id="bodyBlocksContainer" class="template-builder-section"></div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-secondary btn-sm" id="btnAddTextBlock">Agregar bloque de texto</button>
                                <button type="button" class="btn btn-secondary btn-sm" id="btnAddImageBlock">Agregar bloque de imagen</button>
                                <button type="button" class="btn btn-secondary btn-sm" id="btnAddSpacerBlock">Agregar bloque de espacio</button>
                            </div>
                            <input type="hidden" name="cuerpo" id="cuerpoHidden">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Despedida</label>
                            <div id="farewellBlockContainer" class="template-builder-section"></div>
                            <input type="hidden" name="despedida" id="despedidaHidden">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">⏰ Programación de envío automático</label>
                            <div class="p-3 rounded-3 border bg-light">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="schedule_enabled" checked>
                                            <label class="form-check-label fw-semibold" for="schedule_enabled">Activar envío automático</label>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-5" id="scheduleFieldsWrap">
                                        <label class="form-label small mb-1">¿Cada cuántos días se envía?</label>
                                        <select id="schedule_days_preset" class="form-select">
                                            <option value="1">Cada 1 día</option>
                                            <option value="2">Cada 2 días</option>
                                            <option value="3">Cada 3 días</option>
                                            <option value="5">Cada 5 días</option>
                                            <option value="7" selected>Cada 1 semana (7 días)</option>
                                            <option value="9">Cada 9 días</option>
                                            <option value="10">Cada 10 días</option>
                                            <option value="14">Cada 2 semanas (14 días)</option>
                                            <option value="30">Cada 1 mes (30 días)</option>
                                            <option value="custom">Personalizado...</option>
                                        </select>
                                        <div id="schedule_days_custom_wrap" class="mt-2 d-none">
                                            <label class="form-label small mb-1">Número de días entre cada envío</label>
                                            <input type="number" id="schedule_every_days" class="form-control" min="1" max="365" value="7" placeholder="Ej. 21">
                                        </div>
                                    </div>
                                    <div class="col-8 col-md-4" id="scheduleTimeWrap">
                                        <label class="form-label small mb-1">Hora de envío</label>
                                        <input type="time" id="schedule_time" class="form-control" value="09:00">
                                    </div>
                                </div>
                                <div id="scheduleCronPreview" class="mt-3 p-2 rounded-2 border small text-muted bg-white"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted">Variables disponibles para usar en título/cuerpo/despedida: <code>$full_name</code>, <code>$wedding_date</code>, <code>$schedule_button</code> (inserta botón de agenda con tracking)</small>
                        </div>
                        <!-- hidden template id when editing -->
                        <input type="hidden" id="templateId" name="id" value="">
                        <input type="hidden" name="creador_id" value="<?php echo intval($userid); ?>">
                            </form>
                        </div>
                        <div class="template-live-preview">
                            <div class="template-live-preview-head">
                                <div class="title">Previsualización en tiempo real</div>
                                <div class="sub" id="livePreviewVars">$full_name = María Pérez | $wedding_date = 12 de junio de 2026</div>
                            </div>
                            <iframe id="livePreviewFrame" class="template-live-preview-frame"></iframe>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-secondary" id="previewTemplateBtn">Previsualizar</button>
                    <button type="button" class="btn btn-info" id="sendTestTemplateBtn">Enviar test</button>
                    <button type="button" class="btn btn-primary" id="saveTemplateBtn">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Previsualización -->
    <div class="modal fade" id="modalPreview" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPreviewTitle">Previsualización del correo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="overflow:auto; max-height:75vh;">
                    <div id="previewVars" class="mb-2 text-muted small"></div>
                    <div id="previewBody"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Leads enviados por plantilla -->
    <div class="modal fade" id="modalTemplateSentLeads" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTemplateSentLeadsTitle">Leads enviados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="modalTemplateSentLeadsBody" style="max-height:60vh; overflow:auto;">
                    <div class="py-3 text-center">Cargando...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Enviar prueba por correo -->
    <div class="modal fade" id="modalSendTest" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar prueba</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="formSendTest">
                        <div class="mb-3">
                            <label for="testEmail" class="form-label">Correo electrónico</label>
                            <input type="email" class="form-control" id="testEmail" name="testEmail" placeholder="correo@ejemplo.com" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmSendTest">Enviar</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let table;
        let blockSeq = 0;
        let titleBlock = null;
        let farewellBlock = null;
        let bodyBlocks = [];

        const FONT_FAMILIES = [
            'Arial, sans-serif',
            '"Times New Roman", serif',
            'Georgia, serif',
            'Verdana, sans-serif',
            'Tahoma, sans-serif',
            '"Trebuchet MS", sans-serif'
        ];

        const FONT_SIZES = ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px'];

        const DEFAULT_TEXT_STYLE = {
            fontSize: '16px',
            fontWeight: 'normal',
            color: '#1f2937',
            textAlign: 'left',
            fontFamily: 'Arial, sans-serif',
            letterSpacing: '0px',
            lineHeight: '1.5'
        };

        const DEFAULT_SPACER_HEIGHT = 20;

        $(document).ready(function () {
            initTemplateBuilder();
            let livePreviewTimer = null;
            let livePreviewLastHtml = '';
            let livePreviewPendingScroll = { x: 0, y: 0 };

            // init DataTable
            table = $('#templatesTable').DataTable({
                ajax: 'fetch_plantillas_marketing.php',
                columns: [
                    { data: 'id' },
                    { data: 'nombre' },
                    { data: 'asunto' },
                    { 
                        data: 'titulo', 
                        render: function(data) {
                            // Mostrar solo texto plano en la tabla, sin HTML
                            return data ? $('<div>').html(data).text().substring(0, 50) + '...' : '';
                        }
                    },
                    { data: 'created_at' },
                    {
                        data: null,
                        render: function (data) {
                            return formatCronSummary(data);
                        }
                    },
                    {
                        data: null, render: function (data) {
                            return `<button class="me-1 btn-outline-secondary btn btn-sm btn-preview" data-id="${data.id}">Previsualizar</button>` +
                                   `<button class="btn-outline-secondary btn btn-sm btn-view" data-id="${data.id}">Editar</button>` +
                                   `<button class="btn btn-info btn-sm btn-send-test ms-1" data-id="${data.id}">Enviar test</button>`;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data) {
                            return `<button class="btn-outline-primary btn btn-sm btn-view-sent-leads" data-id="${data.id}">ver a que lead se les mandó</button>`;
                        }
                    }
                ]
            });

            const modal = new bootstrap.Modal(document.getElementById('modalTemplate'));

            $('#btnEmojis').on('click', function () {
                Swal.fire({
                    title: 'Emojis permitidos',
                    width: 900,
                    confirmButtonText: 'Cerrar',
                    customClass: {
                        htmlContainer: 'text-start'
                    },
                    html: `
                        <div class="emoji-modal">
                            <div class="mb-3">
                                <div class="emoji-title">EMOJIS “SEGUROS” PARA EMAIL</div>
                                <div class="emoji-subtitle">Alta compatibilidad en la mayoría de clientes.</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="emoji-card">
                                        <h6>😀 Caras básicas</h6>
                                        <div class="emoji-list">😀😃😄😁  😊🙂😉  😌  😎  😕😐  😮😲  😢😭  😠😡</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="emoji-card">
                                        <h6>❤️ Corazones y emociones</h6>
                                        <div class="emoji-list">❤️💔  💙💚💛💜  💕💖  ❣️  💯</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="emoji-card">
                                        <h6>👍 Gestos simples (sin tono de piel)</h6>
                                        <div class="emoji-list">👍👎  👌  ✌️  🤝  👏  🙌  🙏</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="emoji-card">
                                        <h6>✔️ Símbolos y check (muy seguros)</h6>
                                        <div class="emoji-list">✔️☑️  ❌✖️  ➕➖  ➗  ⭐✨🌟  ⚠️  ❗❓  ‼️  ⭕❌  🔴🟢🟡🔵</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="emoji-card">
                                        <h6>➡️ Flechas y direcciones</h6>
                                        <div class="emoji-list">➡️⬅️⬆️⬇️  ↗️↘️↙️↖️  🔼🔽</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="emoji-card">
                                        <h6>📅 Oficina, trabajo y email</h6>
                                        <div class="emoji-list">📧✉️  📩📨  📎📌  📁📂  📄📃  🗂️  📅  ⏰⏱️  🖊️✏️  📞☎️  💼</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="emoji-card">
                                        <h6>🎉 Celebración y avisos positivos</h6>
                                        <div class="emoji-list">🎉🎊  🎈  🏆  🥇🥈🥉  🎁</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="emoji-card">
                                        <h6>🔥 Otros muy usados</h6>
                                        <div class="emoji-list">🔥  💡  🔔  🔒🔓  🛑  🚀</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <span class="emoji-badge">⚠️ Compatibilidad variable</span>
                                <div class="mt-2 emoji-subtitle">Se ven bien en Gmail y Apple Mail, pero pueden fallar en Outlook viejo.</div>
                                <div class="mt-2 emoji-list">🙂‍↔️  🤔  😬  😅  🤗  👀  📍  🧾</div>
                            </div>
                        </div>
                    `
                });
            });

            $('#btnAddTemplate').click(() => {
                $('#formAddTemplate')[0].reset();
                $('#templateId').val('');
                $('#saveTemplateBtn').text('Guardar');
                resetTemplateBuilder();
                $('#cuerpoHidden').val('');
                $('#tituloHidden').val('');
                $('#despedidaHidden').val('');
                $('#schedule_enabled').prop('checked', true);
                setScheduleDaysPreset(7);
                $('#schedule_time').val('09:00');
                updateCronFieldsVisibility();
                updateCronPreview();
                modal.show();
                scheduleLivePreview();
            });

            $('#btnAddTextBlock').click(function () {
                bodyBlocks.push(createTextBlock({ content: '' }));
                renderBodyBlocks();
                scheduleLivePreview();
            });

            $('#btnAddImageBlock').click(function () {
                bodyBlocks.push(createImageBlock({ src: '', align: 'center' }));
                renderBodyBlocks();
                scheduleLivePreview();
            });

            $('#btnAddSpacerBlock').click(function () {
                bodyBlocks.push(createSpacerBlock({ height: DEFAULT_SPACER_HEIGHT }));
                renderBodyBlocks();
                scheduleLivePreview();
            });

            $('#modalTemplate').on('input change', 'input, select, .tb-editable', function () {
                scheduleLivePreview();
            });

            $('#bodyBlocksContainer').on('input change', '.tb-spacer-height', function () {
                const id = parseInt($(this).closest('.tb-block').data('blockId'), 10);
                const block = findBodyBlock(id);
                if (!block || block.type !== 'spacer') return;

                let height = parseInt($(this).val(), 10);
                if (isNaN(height)) height = DEFAULT_SPACER_HEIGHT;
                height = Math.max(4, Math.min(120, height));
                block.height = height;
                $(this).val(height);

                const $preview = $(this).closest('.tb-block').find('.tb-spacer-preview');
                $preview.css('height', `${height}px`);
                $(this).closest('.tb-block').find('.tb-spacer-height-value').text(`${height}px`);
            });

            $('#bodyBlocksContainer').on('input', '.tb-editable', function () {
                const id = parseInt($(this).closest('.tb-block').data('blockId'), 10);
                const block = findBodyBlock(id);
                if (!block || block.type !== 'text') return;
                block.content = $(this).html();
            });

            $('#titleBlockContainer').on('input', '.tb-editable', function () {
                if (!titleBlock) return;
                titleBlock.content = $(this).html();
            });

            $('#farewellBlockContainer').on('input', '.tb-editable', function () {
                if (!farewellBlock) return;
                farewellBlock.content = $(this).html();
            });

            $('#modalTemplate').on('change input', '.tb-font-size, .tb-font-weight, .tb-text-align, .tb-font-family, .tb-text-color, .tb-letter-spacing, .tb-line-height', function () {
                const $block = $(this).closest('.tb-block');
                const blockId = parseInt($block.data('blockId'), 10);
                const section = String($block.data('section') || 'body');
                const block = getBlockBySectionAndId(section, blockId);
                if (!block || block.type !== 'text') return;
                const $editable = $block.find('.tb-editable');

                normalizeEditableInlineStyles($editable);

                block.style.fontSize = $block.find('.tb-font-size').val() || DEFAULT_TEXT_STYLE.fontSize;
                block.style.fontWeight = $block.find('.tb-font-weight').val() || DEFAULT_TEXT_STYLE.fontWeight;
                block.style.color = $block.find('.tb-text-color').val() || DEFAULT_TEXT_STYLE.color;
                block.style.textAlign = $block.find('.tb-text-align').val() || DEFAULT_TEXT_STYLE.textAlign;
                block.style.fontFamily = $block.find('.tb-font-family').val() || DEFAULT_TEXT_STYLE.fontFamily;
                const letterSpacingRaw = parseFloat($block.find('.tb-letter-spacing').val());
                const lineHeightRaw = parseFloat($block.find('.tb-line-height').val());
                const letterSpacingSafe = isNaN(letterSpacingRaw) ? 0 : Math.max(-2, Math.min(10, letterSpacingRaw));
                const lineHeightSafe = isNaN(lineHeightRaw) ? 1.5 : Math.max(1, Math.min(3, lineHeightRaw));
                block.style.letterSpacing = `${letterSpacingSafe}px`;
                block.style.lineHeight = String(lineHeightSafe);

                $block.find('.tb-letter-spacing').val(letterSpacingSafe);
                $block.find('.tb-line-height').val(lineHeightSafe);

                applyTextStyles($editable, block.style);
                block.content = $editable.html();
            });

            $('#bodyBlocksContainer').on('input', '.tb-image-url', function () {
                const id = parseInt($(this).closest('.tb-block').data('blockId'), 10);
                const block = findBodyBlock(id);
                if (!block || block.type !== 'image') return;
                block.src = ($(this).val() || '').trim();
                updateImagePreview($(this).closest('.tb-block'), block);
            });

            $('#bodyBlocksContainer').on('change', '.tb-image-file', function (event) {
                const id = parseInt($(this).closest('.tb-block').data('blockId'), 10);
                const block = findBodyBlock(id);
                if (!block || block.type !== 'image') return;

                const file = event.target.files && event.target.files[0];
                if (!file) return;

                const $inputFile = $(this);
                const $block = $inputFile.closest('.tb-block');
                const formData = new FormData();
                formData.append('upload', file);

                $inputFile.prop('disabled', true);
                $block.find('.tb-image-preview').html('<div class="text-muted small">Subiendo imagen...</div>');

                $.ajax({
                    url: 'upload_image.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                }).done(function (resp) {
                    const imageUrl = (resp && (resp.default || resp.url)) ? String(resp.default || resp.url).trim() : '';
                    if (!imageUrl) {
                        Swal.fire('Error', 'No se recibió la URL de la imagen', 'error');
                        updateImagePreview($block, block);
                        return;
                    }
                    block.src = imageUrl;
                    $block.find('.tb-image-url').val(block.src);
                    updateImagePreview($block, block);
                    scheduleLivePreview();
                }).fail(function (xhr) {
                    const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                        ? (xhr.responseJSON.error || xhr.responseJSON.message)
                        : 'No se pudo subir la imagen';
                    Swal.fire('Error', msg, 'error');
                    updateImagePreview($block, block);
                }).always(function () {
                    $inputFile.prop('disabled', false);
                    $inputFile.val('');
                });
            });

            $('#bodyBlocksContainer').on('change', '.tb-image-align', function () {
                const id = parseInt($(this).closest('.tb-block').data('blockId'), 10);
                const block = findBodyBlock(id);
                if (!block || block.type !== 'image') return;
                block.align = $(this).val() || 'center';
                updateImagePreview($(this).closest('.tb-block'), block);
            });

            $('#bodyBlocksContainer').on('input change', '.tb-image-width', function () {
                const id = parseInt($(this).closest('.tb-block').data('blockId'), 10);
                const block = findBodyBlock(id);
                if (!block || block.type !== 'image') return;

                let widthPercent = parseInt($(this).val(), 10);
                if (isNaN(widthPercent)) widthPercent = 100;
                widthPercent = Math.max(20, Math.min(100, widthPercent));
                block.widthPercent = widthPercent;

                const $b = $(this).closest('.tb-block');
                $b.find('.tb-image-width').val(widthPercent);
                $b.find('.tb-image-width-value').text(`${widthPercent}%`);
                updateImagePreview($b, block);
            });

            $('#bodyBlocksContainer').on('click', '.btn-remove-block', function () {
                const id = parseInt($(this).closest('.tb-block').data('blockId'), 10);
                bodyBlocks = bodyBlocks.filter(b => b.id !== id);
                renderBodyBlocks();
                scheduleLivePreview();
            });

            $('#bodyBlocksContainer').on('click', '.btn-move-up', function () {
                const id = parseInt($(this).closest('.tb-block').data('blockId'), 10);
                const idx = bodyBlocks.findIndex(b => b.id === id);
                if (idx <= 0) return;
                const temp = bodyBlocks[idx - 1];
                bodyBlocks[idx - 1] = bodyBlocks[idx];
                bodyBlocks[idx] = temp;
                renderBodyBlocks();
                scheduleLivePreview();
            });

            $('#bodyBlocksContainer').on('click', '.btn-move-down', function () {
                const id = parseInt($(this).closest('.tb-block').data('blockId'), 10);
                const idx = bodyBlocks.findIndex(b => b.id === id);
                if (idx < 0 || idx >= bodyBlocks.length - 1) return;
                const temp = bodyBlocks[idx + 1];
                bodyBlocks[idx + 1] = bodyBlocks[idx];
                bodyBlocks[idx] = temp;
                renderBodyBlocks();
                scheduleLivePreview();
            });

            // Cron preset dropdown
            $('#schedule_days_preset').on('change', function () {
                const val = $(this).val();
                if (val === 'custom') {
                    $('#schedule_days_custom_wrap').removeClass('d-none');
                } else {
                    $('#schedule_days_custom_wrap').addClass('d-none');
                    $('#schedule_every_days').val(val);
                }
                updateCronPreview();
            });

            $('#schedule_every_days').on('input', function () {
                updateCronPreview();
            });

            $('#schedule_time').on('change input', function () {
                updateCronPreview();
            });

            $('#schedule_enabled').on('change', function () {
                updateCronFieldsVisibility();
                updateCronPreview();
            });

            $('#saveTemplateBtn').click(function () {
                const nombre = $('#nombre').val().trim();
                const asunto = $('#asunto').val().trim();
                const currentContent = serializeTemplateContent();
                const titulo = currentContent.titulo;
                const cuerpo = currentContent.cuerpo;
                const despedida = currentContent.despedida;
                const schedule_enabled = $('#schedule_enabled').is(':checked') ? 1 : 0;
                const schedule_every_days = getCronDays();
                const schedule_time = $('#schedule_time').val() || '09:00';
                const schedule_repeat = 0;

                // Asignar a los campos ocultos por si se necesita
                $('#tituloHidden').val(titulo);
                $('#cuerpoHidden').val(cuerpo);
                $('#despedidaHidden').val(despedida);

                if (!nombre || !stripHtml(titulo).trim()) {
                    Swal.fire('Error', 'Nombre y título son obligatorios', 'error');
                    return;
                }

                $('#saveTemplateBtn').prop('disabled', true);
                const templateId = $('#templateId').val() || 0;

                $.post('guardar_plantilla_marketing.php', { 
                    id: templateId, 
                    nombre, 
                    asunto, 
                    titulo, 
                    cuerpo, 
                    despedida, 
                    schedule_enabled,
                    schedule_every_days,
                    schedule_time,
                    schedule_repeat
                }, function (resp) {
                    try {
                        const res = (typeof resp === 'string') ? JSON.parse(resp) : resp;
                        if (res.status === 'success') {
                            const createdAt = res.created_at || new Date().toISOString().slice(0,19).replace('T',' ');
                            const newRow = { 
                                id: res.id, 
                                nombre: nombre, 
                                asunto: asunto, 
                                titulo: titulo, 
                                created_at: createdAt, 
                                schedule_enabled,
                                schedule_every_days,
                                schedule_time,
                                schedule_repeat
                            };

                            if (res.isUpdate) {
                                updateRowInTable(res.id, newRow);
                                Swal.fire('¡Actualizado!', res.message, 'success');
                            } else {
                                table.row.add(newRow).draw(false);
                                Swal.fire('¡Guardado!', res.message, 'success');
                            }
                            modal.hide();
                            $('#formAddTemplate')[0].reset();
                            $('#templateId').val('');
                            $('#saveTemplateBtn').text('Guardar');
                            resetTemplateBuilder();
                            setScheduleDaysPreset(7);
                            $('#schedule_time').val('09:00');
                            updateCronFieldsVisibility();
                            updateCronPreview();
                        } else {
                            Swal.fire('Error', res.message || 'No se pudo guardar', 'error');
                        }
                    } catch (e) {
                        console.error(e);
                        Swal.fire('Error', 'Respuesta inválida del servidor', 'error');
                    }
                }).fail(function (xhr) {
                    Swal.fire('Error', 'Error al guardar plantilla', 'error');
                    console.error(xhr);
                }).always(function(){
                    $('#saveTemplateBtn').prop('disabled', false);
                });
            });

            // Editar plantilla
            $('#templatesTable').on('click', '.btn-view', function () {
                const id = $(this).data('id');
                $.getJSON('fetch_plantillas_marketing.php', { id }, function (resp) {
                    const row = resp.data && resp.data.find(r => r.id == id);
                    if (!row) { Swal.fire('Error', 'No encontrado', 'error'); return; }
                    $('#nombre').val(row.nombre);
                    $('#asunto').val(row.asunto || '');
                    loadTemplateIntoBuilder(row.titulo || '', row.cuerpo || '', row.despedida || '');
                    $('#schedule_enabled').prop('checked', parseInt(row.schedule_enabled || 0) === 1);
                    setScheduleDaysPreset(parseInt(row.schedule_every_days || 7));
                    $('#schedule_time').val((row.schedule_time || '09:00').substring(0, 5));
                    updateCronFieldsVisibility();
                    updateCronPreview();
                    $('#templateId').val(row.id);
                    $('#saveTemplateBtn').text('Actualizar');
                    modal.show();
                    scheduleLivePreview();
                }).fail(() => Swal.fire('Error', 'No se pudo obtener la plantilla', 'error'));
            });

            document.getElementById('modalTemplate').addEventListener('shown.bs.modal', function () {
                scheduleLivePreview();
            });

            // Previsualizar desde la tabla
            $('#templatesTable').on('click', '.btn-preview', function () {
                const id = $(this).data('id');
                $.getJSON('fetch_plantillas_marketing.php', { id }, function (resp) {
                    const row = resp.data && resp.data.find(r => r.id == id);
                    if (!row) { Swal.fire('Error', 'No encontrado', 'error'); return; }
                    const replacements = getSampleReplacements();
                    const html = buildEmailHtml({ 
                        asunto: replacePlaceholders(row.asunto || '', replacements), 
                        titulo: replacePlaceholders(row.titulo || '', replacements), 
                        cuerpo: replacePlaceholders(row.cuerpo || '', replacements), 
                        despedida: replacePlaceholders(row.despedida || '', replacements), 
                        templateId: row.id 
                    });
                    $('#previewBody').html(html);
                    const visibleKeys = ['$full_name', '$wedding_date'];
                    $('#previewVars').text(visibleKeys.filter(k => replacements[k] !== undefined).map(k => `${k} = ${replacements[k]}`).join(' | '));
                    $('#modalPreviewTitle').text('Previsualización — ' + escapeHtml(row.nombre || 'Plantilla'));
                    const pm = new bootstrap.Modal(document.getElementById('modalPreview'));
                    pm.show();
                }).fail(() => Swal.fire('Error', 'No se pudo obtener la plantilla', 'error'));
            });

            // Previsualizar desde el modal (formulario)
            $('#previewTemplateBtn').click(function () {
                const nombre = $('#nombre').val().trim();
                const asunto = $('#asunto').val().trim();
                const currentContent = serializeTemplateContent();
                const titulo = currentContent.titulo;
                const cuerpo = currentContent.cuerpo;
                const despedida = currentContent.despedida;

                const replacements = getSampleReplacements();
                const html = buildEmailHtml({ 
                    asunto: replacePlaceholders(asunto, replacements), 
                    titulo: replacePlaceholders(titulo, replacements), 
                    cuerpo: replacePlaceholders(cuerpo, replacements), 
                    despedida: replacePlaceholders(despedida, replacements) 
                });
                $('#previewBody').html(html);
                const visibleKeys = ['$full_name', '$wedding_date'];
                $('#previewVars').text(visibleKeys.filter(k => replacements[k] !== undefined).map(k => `${k} = ${replacements[k]}`).join(' | '));
                $('#modalPreviewTitle').text('Previsualización — ' + escapeHtml(nombre || 'Plantilla'));
                const pm = new bootstrap.Modal(document.getElementById('modalPreview'));
                pm.show();
            });

            // Ver leads enviados
            $('#templatesTable').on('click', '.btn-view-sent-leads', function () {
                const id = $(this).data('id');
                const $body = $('#modalTemplateSentLeadsBody');
                $('#modalTemplateSentLeadsTitle').text('Leads enviados — Plantilla ' + id);
                $body.html('<div class="py-3 text-center">Cargando...</div>');
                const modal = new bootstrap.Modal(document.getElementById('modalTemplateSentLeads'));
                modal.show();

                $.post('fetch_template_sent_leads.php', { template_id: id }, function (resp) {
                    if (!resp || !resp.success) {
                        $body.html('<div class="p-3 text-danger">Error: ' + (resp && resp.message ? resp.message : 'Sin datos') + '</div>');
                        return;
                    }
                    const rows = resp.data || [];
                    if (!rows.length) {
                        $body.html('<div class="p-3 text-muted">No se encontraron envíos para esta plantilla.</div>');
                        return;
                    }
                    let html = '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Lead ID</th><th>Tabla</th><th>Nombre</th><th>Correo</th><th>Enviado</th><th>Éxito</th></tr></thead><tbody>';
                    rows.forEach(function (r) {
                        const sentAt = r.sent_at ? r.sent_at : '';
                        const success = r.success == 1 ? '<span class="text-success">Sí</span>' : '<span class="text-danger">No</span>';
                        const name = r.lead_name ? $('<div>').text(r.lead_name).html() : '';
                        const correo = r.email ? $('<div>').text(r.email).html() : '';
                        html += '<tr><td>' + $('<div>').text(r.lead_id).html() + '</td><td>' + $('<div>').text(r.tabla_origen).html() + '</td><td>' + name + '</td><td>' + correo + '</td><td>' + $('<div>').text(sentAt).html() + '</td><td>' + success + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    $body.html(html);
                }, 'json').fail(function () {
                    $body.html('<div class="p-3 text-danger">Error al obtener datos del servidor</div>');
                });
            });

            // Botón de enviar test (desde modal de plantilla)
            $('#sendTestTemplateBtn').click(function () {
                const templateId = $('#templateId').val() || '';
                const nombre = $('#nombre').val().trim();
                const asunto = $('#asunto').val().trim();
                const currentContent = serializeTemplateContent();
                const titulo = currentContent.titulo;
                const cuerpo = currentContent.cuerpo;
                const despedida = currentContent.despedida;

                $('#testEmail').data({
                    templateId: templateId,
                    nombre: nombre,
                    asunto: asunto,
                    titulo: titulo,
                    cuerpo: cuerpo,
                    despedida: despedida,
                    isFromModal: true
                });

                $('#testEmail').val('');
                const modalTest = new bootstrap.Modal(document.getElementById('modalSendTest'));
                modalTest.show();
                setTimeout(() => $('#testEmail').focus(), 100);
            });

            // Botón de enviar test (desde tabla)
            $('#templatesTable').on('click', '.btn-send-test', function () {
                const templateId = $(this).data('id');
                $('#testEmail').data({
                    templateId: templateId,
                    isFromModal: false
                });
                $('#testEmail').val('');
                const modalTest = new bootstrap.Modal(document.getElementById('modalSendTest'));
                modalTest.show();
                setTimeout(() => $('#testEmail').focus(), 100);
            });

            // Confirmar envío de test
            $('#btnConfirmSendTest').click(function () {
                const testEmail = $('#testEmail').val().trim();
                if (!testEmail) {
                    Swal.fire('Error', 'Ingresa un correo válido', 'error');
                    return;
                }

                const data = $('#testEmail').data();
                const templateId = data.templateId || '';
                const isFromModal = data.isFromModal || false;

                const modal = bootstrap.Modal.getInstance(document.getElementById('modalSendTest'));
                if (modal) modal.hide();

                Swal.fire({title: 'Enviando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

                let payload;
                if (isFromModal) {
                    payload = templateId 
                        ? { template_id: templateId, test_email: testEmail } 
                        : { asunto: data.asunto, titulo: data.titulo, cuerpo: data.cuerpo, despedida: data.despedida, test_email: testEmail };
                } else {
                    payload = { template_id: templateId, test_email: testEmail };
                }

                $.post('send_template_test.php', payload, function(resp){
                    Swal.close();
                    try {
                        const r = (typeof resp === 'string') ? JSON.parse(resp) : resp;
                        if (r.success) {
                            Swal.fire('¡Enviado!', r.message || 'Correo de prueba enviado', 'success');
                        } else {
                            Swal.fire('Error', r.message || 'No se pudo enviar prueba', 'error');
                        }
                    } catch(e) {
                        console.error(e);
                        Swal.fire('Error', 'Respuesta inválida del servidor', 'error');
                    }
                }).fail(function(xhr){
                    Swal.close();
                    Swal.fire('Error', 'Error en el servidor al enviar la prueba', 'error');
                });
            });

            // Helper: actualizar fila en DataTable
            function updateRowInTable(id, newRow) {
                table.rows().every(function () {
                    const d = this.data();
                    if (parseInt(d.id) === parseInt(id)) {
                        this.data(newRow);
                    }
                });
                table.draw(false);
            }

            // ─── Cron helper functions ───────────────────────────────────

            /** Returns number of days from the preset dropdown or the custom input */
            function getCronDays() {
                const preset = $('#schedule_days_preset').val();
                if (preset === 'custom') {
                    return Math.max(1, parseInt($('#schedule_every_days').val(), 10) || 1);
                }
                return Math.max(1, parseInt(preset, 10) || 1);
            }

            /** Sets the preset dropdown (and shows custom input if needed) */
            function setScheduleDaysPreset(days) {
                const knownValues = ['1','2','3','5','7','9','10','14','30'];
                const safeDays = Math.max(1, parseInt(days, 10) || 7);
                const daysStr = String(safeDays);
                if (knownValues.includes(daysStr)) {
                    $('#schedule_days_preset').val(daysStr);
                    $('#schedule_days_custom_wrap').addClass('d-none');
                    $('#schedule_every_days').val(safeDays);
                } else {
                    $('#schedule_days_preset').val('custom');
                    $('#schedule_days_custom_wrap').removeClass('d-none');
                    $('#schedule_every_days').val(safeDays);
                }
            }

            /** Show/hide schedule fields based on enabled checkbox */
            function updateCronFieldsVisibility() {
                const enabled = $('#schedule_enabled').is(':checked');
                $('#scheduleFieldsWrap, #scheduleTimeWrap').css('opacity', enabled ? '1' : '0.45');
                $('#scheduleFieldsWrap select, #scheduleFieldsWrap input, #scheduleTimeWrap input').prop('disabled', !enabled);
            }

            /** Updates the human-readable cron preview box inside the modal */
            function updateCronPreview() {
                const enabled = $('#schedule_enabled').is(':checked');
                const days = getCronDays();
                const time = ($('#schedule_time').val() || '09:00').substring(0, 5);

                if (!enabled) {
                    $('#scheduleCronPreview').html('<i class="fa fa-ban me-1"></i> Envío automático <strong>desactivado</strong>.');
                    return;
                }

                let whenText;
                if (days === 1) {
                    whenText = '<strong>cada 1 día</strong>';
                } else {
                    whenText = `<strong>cada ${days} días</strong>`;
                }

                $('#scheduleCronPreview').html(`
                    <i class="fa fa-clock me-1"></i>
                    Se enviará <strong>a las ${escapeHtml(time)}</strong>, ${whenText}, de forma continua y automática.
                `);
            }

            /** Format cron config for the DataTable Programación column */
            function formatCronSummary(data) {
                const enabled = parseInt(data.schedule_enabled || 0);
                if (!enabled) return '<span class="text-muted">Inactiva</span>';
                const days = Math.max(1, parseInt(data.schedule_every_days, 10) || 7);
                const time = (data.schedule_time || '09:00').substring(0, 5);
                const dayLabel = days === 1 ? 'Cada 1 día' : 'Cada ' + days + ' días';
                return `<span class="text-success"><i class="fa fa-clock"></i> ${escapeHtml(dayLabel)} &bull; ${escapeHtml(time)}</span>`;
            }

            // Initialize on page load
            updateCronFieldsVisibility();
            updateCronPreview();

            function nextBlockId() {
                blockSeq += 1;
                return blockSeq;
            }

            function createTextBlock(config = {}) {
                return {
                    id: nextBlockId(),
                    type: 'text',
                    content: config.content || '',
                    style: Object.assign({}, DEFAULT_TEXT_STYLE, config.style || {})
                };
            }

            function createImageBlock(config = {}) {
                let widthPercent = parseInt(config.widthPercent, 10);
                if (isNaN(widthPercent)) widthPercent = 100;
                widthPercent = Math.max(20, Math.min(100, widthPercent));
                return {
                    id: nextBlockId(),
                    type: 'image',
                    src: config.src || '',
                    align: config.align || 'center',
                    widthPercent: widthPercent
                };
            }

            function createSpacerBlock(config = {}) {
                let height = parseInt(config.height, 10);
                if (isNaN(height)) height = DEFAULT_SPACER_HEIGHT;
                height = Math.max(4, Math.min(120, height));
                return {
                    id: nextBlockId(),
                    type: 'spacer',
                    height: height
                };
            }

            function initTemplateBuilder() {
                resetTemplateBuilder();
            }

            function resetTemplateBuilder() {
                titleBlock = createTextBlock({ content: '' });
                farewellBlock = createTextBlock({ content: '' });
                bodyBlocks = [createTextBlock({ content: '' })];
                renderFixedBlock('#titleBlockContainer', titleBlock, 'Título del correo...','title');
                renderFixedBlock('#farewellBlockContainer', farewellBlock, 'Despedida del correo...','farewell');
                renderBodyBlocks();
            }

            function renderFixedBlock(containerSelector, block, placeholder, section) {
                const html = renderTextBlockHtml(block, {
                    removable: false,
                    movable: false,
                    placeholder: placeholder,
                    section: section
                });
                $(containerSelector).html(html);
                const $editable = $(containerSelector).find('.tb-editable');
                applyTextStyles($editable, block.style);
            }

            function renderBodyBlocks() {
                const $container = $('#bodyBlocksContainer');
                if (!bodyBlocks.length) {
                    $container.html('<div class="text-muted small">No hay bloques. Agrega un bloque de texto o imagen.</div>');
                    return;
                }

                let html = '';
                bodyBlocks.forEach(function (block) {
                    if (block.type === 'text') {
                        html += renderTextBlockHtml(block, {
                            removable: true,
                            movable: true,
                            placeholder: 'Escribe aquí el texto del bloque...',
                            section: 'body'
                        });
                    } else if (block.type === 'spacer') {
                        html += renderSpacerBlockHtml(block);
                    } else {
                        html += renderImageBlockHtml(block);
                    }
                });
                $container.html(html);

                $container.find('.tb-block[data-block-type="text"]').each(function () {
                    const id = parseInt($(this).data('blockId'), 10);
                    const block = findBodyBlock(id);
                    if (!block || block.type !== 'text') return;
                    applyTextStyles($(this).find('.tb-editable'), block.style);
                });

                $container.find('.tb-block[data-block-type="image"]').each(function () {
                    const id = parseInt($(this).data('blockId'), 10);
                    const block = findBodyBlock(id);
                    if (!block || block.type !== 'image') return;
                    updateImagePreview($(this), block);
                });

                $container.find('.tb-block[data-block-type="spacer"]').each(function () {
                    const id = parseInt($(this).data('blockId'), 10);
                    const block = findBodyBlock(id);
                    if (!block || block.type !== 'spacer') return;
                    $(this).find('.tb-spacer-preview').css('height', `${block.height}px`);
                });
            }

            function renderTextBlockHtml(block, config) {
                const section = config.section || 'body';
                return `
                    <div class="tb-block" data-block-id="${block.id}" data-section="${section}" data-block-type="text">
                        <div class="tb-toolbar">
                            <select class="form-select form-select-sm tb-font-size">${FONT_SIZES.map(s => `<option value="${s}" ${block.style.fontSize === s ? 'selected' : ''}>${s}</option>`).join('')}</select>
                            <select class="form-select form-select-sm tb-font-weight">
                                <option value="normal" ${block.style.fontWeight === 'normal' ? 'selected' : ''}>Normal</option>
                                <option value="bold" ${block.style.fontWeight === 'bold' ? 'selected' : ''}>Negrita</option>
                            </select>
                            <input type="color" class="form-control form-control-color tb-text-color" value="${escapeHtml(block.style.color || DEFAULT_TEXT_STYLE.color)}" title="Color de texto">
                            <select class="form-select form-select-sm tb-text-align">
                                <option value="left" ${block.style.textAlign === 'left' ? 'selected' : ''}>Izquierda</option>
                                <option value="center" ${block.style.textAlign === 'center' ? 'selected' : ''}>Centro</option>
                                <option value="right" ${block.style.textAlign === 'right' ? 'selected' : ''}>Derecha</option>
                                <option value="justify" ${block.style.textAlign === 'justify' ? 'selected' : ''}>Justificado</option>
                            </select>
                            <select class="form-select form-select-sm tb-font-family">${FONT_FAMILIES.map(ff => `<option value="${escapeHtml(ff)}" ${block.style.fontFamily === ff ? 'selected' : ''}>${escapeHtml(ff.replace(/,\s*.+$/, ''))}</option>`).join('')}</select>
                            <input type="number" class="form-control form-control-sm tb-letter-spacing" min="-2" max="10" step="0.1" value="${parseFloat(block.style.letterSpacing || '0')}">
                            <input type="number" class="form-control form-control-sm tb-line-height" min="1" max="3" step="0.1" value="${parseFloat(block.style.lineHeight || '1.5')}">
                        </div>
                        <div class="tb-actions d-flex justify-content-end gap-2 mb-2">
                            ${config.movable ? '<button type="button" class="btn btn-sm btn-outline btn-move-up">↑</button><button type="button" class="btn btn-sm btn-outline btn-move-down">↓</button>' : ''}
                            ${config.removable ? '<button type="button" class="btn btn-sm btn-danger btn-remove-block">Eliminar</button>' : ''}
                        </div>
                        <div class="tb-editable" contenteditable="true" data-placeholder="${escapeHtml(config.placeholder || '')}">${sanitizeEditorHtml(block.content || '')}</div>
                    </div>
                `;
            }

            function renderImageBlockHtml(block) {
                return `
                    <div class="tb-block" data-block-id="${block.id}" data-section="body" data-block-type="image">
                        <div class="tb-block-note">Bloque de imagen</div>
                        <div class="tb-image-row">
                            <input type="text" class="form-control form-control-sm tb-image-url" placeholder="Pega URL de imagen" value="${escapeHtml(block.src || '')}">
                            <input type="file" class="form-control form-control-sm tb-image-file" accept="image/*">
                            <select class="form-select form-select-sm tb-image-align">
                                <option value="left" ${block.align === 'left' ? 'selected' : ''}>Izquierda</option>
                                <option value="center" ${block.align === 'center' ? 'selected' : ''}>Centro</option>
                                <option value="right" ${block.align === 'right' ? 'selected' : ''}>Derecha</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <label class="form-label mb-0">Tamaño</label>
                            <input type="range" class="form-range tb-image-width" min="20" max="100" step="1" value="${block.widthPercent || 100}" style="max-width:220px;">
                            <span class="text-muted small tb-image-width-value">${block.widthPercent || 100}%</span>
                        </div>
                        <div class="tb-image-preview-wrap tb-image-preview"></div>
                        <div class="tb-actions d-flex justify-content-end gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline btn-move-up">↑</button>
                            <button type="button" class="btn btn-sm btn-outline btn-move-down">↓</button>
                            <button type="button" class="btn btn-sm btn-danger btn-remove-block">Eliminar</button>
                        </div>
                    </div>
                `;
            }

            function renderSpacerBlockHtml(block) {
                return `
                    <div class="tb-block" data-block-id="${block.id}" data-section="body" data-block-type="spacer">
                        <div class="tb-block-note">Bloque de espacio</div>
                        <div class="tb-spacer-box">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <label class="form-label mb-0">Alto</label>
                                <input type="number" class="form-control form-control-sm tb-spacer-height" value="${block.height}" min="4" max="120" step="1" style="max-width:110px;">
                                <span class="text-muted small tb-spacer-height-value">${block.height}px</span>
                            </div>
                            <div class="tb-spacer-preview" style="height:${block.height}px;"></div>
                        </div>
                        <div class="tb-actions d-flex justify-content-end gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline btn-move-up">↑</button>
                            <button type="button" class="btn btn-sm btn-outline btn-move-down">↓</button>
                            <button type="button" class="btn btn-sm btn-danger btn-remove-block">Eliminar</button>
                        </div>
                    </div>
                `;
            }

            function updateImagePreview($block, block) {
                const align = block.align || 'center';
                const $preview = $block.find('.tb-image-preview');
                if (!block.src) {
                    $preview.html('<div class="text-muted small">Sin imagen</div>');
                    $preview.css('text-align', 'left');
                    return;
                }
                const widthPercent = Math.max(20, Math.min(100, parseInt(block.widthPercent, 10) || 100));
                $preview.css('text-align', align);
                $preview.html(`<img src="${escapeHtml(block.src)}" alt="Imagen del bloque" style="width:${widthPercent}%;max-width:100%;height:auto;">`);
            }

            function findBodyBlock(id) {
                return bodyBlocks.find(b => b.id === id) || null;
            }

            function getBlockBySectionAndId(section, id) {
                if (section === 'title') return titleBlock && titleBlock.id === id ? titleBlock : null;
                if (section === 'farewell') return farewellBlock && farewellBlock.id === id ? farewellBlock : null;
                return findBodyBlock(id);
            }

            function applyTextStyles($el, style) {
                $el.css({
                    fontSize: style.fontSize || DEFAULT_TEXT_STYLE.fontSize,
                    fontWeight: style.fontWeight || DEFAULT_TEXT_STYLE.fontWeight,
                    color: style.color || DEFAULT_TEXT_STYLE.color,
                    textAlign: style.textAlign || DEFAULT_TEXT_STYLE.textAlign,
                    fontFamily: style.fontFamily || DEFAULT_TEXT_STYLE.fontFamily,
                    letterSpacing: style.letterSpacing || DEFAULT_TEXT_STYLE.letterSpacing,
                    lineHeight: style.lineHeight || DEFAULT_TEXT_STYLE.lineHeight
                });
            }

            function serializeTemplateContent() {
                const titulo = buildTextBlockOutput(titleBlock);
                const despedida = buildTextBlockOutput(farewellBlock);
                const cuerpo = bodyBlocks.map(function (block) {
                    if (block.type === 'spacer') {
                        const h = Math.max(4, Math.min(120, parseInt(block.height, 10) || DEFAULT_SPACER_HEIGHT));
                        return `<div style="height:${h}px;line-height:${h}px;">&nbsp;</div>`;
                    }
                    if (block.type === 'image') {
                        if (!block.src) return '';
                        const align = ['left', 'center', 'right'].includes(block.align) ? block.align : 'center';
                        const widthPercent = Math.max(20, Math.min(100, parseInt(block.widthPercent, 10) || 100));
                        return `<div style="text-align:${align};margin:12px 0;"><img src="${escapeHtml(block.src)}" alt="" style="width:${widthPercent}%;max-width:100%;height:auto;display:inline-block;"></div>`;
                    }
                    return buildTextBlockOutput(block);
                }).filter(Boolean).join('');

                return { titulo, cuerpo, despedida };
            }

            function buildTextBlockOutput(block) {
                if (!block) return '';
                const style = block.style || DEFAULT_TEXT_STYLE;
                const safeContent = sanitizeEditorHtml(block.content || '');
                return `<div style="font-size:${escapeHtml(style.fontSize || DEFAULT_TEXT_STYLE.fontSize)};font-weight:${escapeHtml(style.fontWeight || DEFAULT_TEXT_STYLE.fontWeight)};color:${escapeHtml(style.color || DEFAULT_TEXT_STYLE.color)};text-align:${escapeHtml(style.textAlign || DEFAULT_TEXT_STYLE.textAlign)};font-family:${escapeHtml(style.fontFamily || DEFAULT_TEXT_STYLE.fontFamily)};letter-spacing:${escapeHtml(style.letterSpacing || DEFAULT_TEXT_STYLE.letterSpacing)};line-height:${escapeHtml(style.lineHeight || DEFAULT_TEXT_STYLE.lineHeight)};">${safeContent}</div>`;
            }

            function sanitizeEditorHtml(html) {
                const $tmp = $('<div>').html(html || '');
                $tmp.find('script,style,iframe,object,embed').remove();
                $tmp.find('*').each(function () {
                    const attrs = this.attributes ? Array.from(this.attributes) : [];
                    attrs.forEach(attr => {
                        if (/^on/i.test(attr.name)) {
                            $(this).removeAttr(attr.name);
                        }
                    });
                });
                return $tmp.html();
            }

            function normalizeEditableInlineStyles($editable) {
                if (!$editable || !$editable.length) return;

                const blockedProps = {
                    'font-family': true,
                    'font-size': true,
                    'font-weight': true,
                    'color': true,
                    'line-height': true,
                    'letter-spacing': true,
                    'text-align': true,
                    'background-color': true
                };

                $editable.find('*').each(function () {
                    const styleText = String($(this).attr('style') || '');
                    if (!styleText) return;

                    const kept = styleText
                        .split(';')
                        .map(part => part.trim())
                        .filter(Boolean)
                        .filter(part => {
                            const idx = part.indexOf(':');
                            if (idx < 0) return false;
                            const prop = part.slice(0, idx).trim().toLowerCase();
                            return !blockedProps[prop];
                        });

                    if (kept.length) {
                        $(this).attr('style', kept.join('; '));
                    } else {
                        $(this).removeAttr('style');
                    }
                });
            }

            function scheduleLivePreview() {
                if (livePreviewTimer) {
                    clearTimeout(livePreviewTimer);
                }
                livePreviewTimer = setTimeout(renderLivePreview, 120);
            }

            function renderLivePreview() {
                const frame = document.getElementById('livePreviewFrame');
                if (!frame) return;

                const currentWin = frame.contentWindow;
                if (currentWin && currentWin.document) {
                    const doc = currentWin.document;
                    const de = doc.documentElement || {};
                    const b = doc.body || {};
                    livePreviewPendingScroll = {
                        x: currentWin.pageXOffset || de.scrollLeft || b.scrollLeft || 0,
                        y: currentWin.pageYOffset || de.scrollTop || b.scrollTop || 0
                    };
                }

                const asunto = ($('#asunto').val() || '').trim();
                const currentContent = serializeTemplateContent();
                const replacements = getSampleReplacements();

                const html = buildEmailHtml({
                    asunto: replacePlaceholders(asunto, replacements),
                    titulo: replacePlaceholders(currentContent.titulo || '', replacements),
                    cuerpo: replacePlaceholders(currentContent.cuerpo || '', replacements),
                    despedida: replacePlaceholders(currentContent.despedida || '', replacements)
                });

                if (html !== livePreviewLastHtml) {
                    frame.onload = function () {
                        const win = frame.contentWindow;
                        if (!win || !win.document) return;
                        const targetX = livePreviewPendingScroll.x || 0;
                        const targetY = livePreviewPendingScroll.y || 0;
                        setTimeout(function () {
                            try {
                                win.scrollTo(targetX, targetY);
                            } catch (e) {}
                        }, 0);
                    };
                    frame.srcdoc = html;
                    livePreviewLastHtml = html;
                }

                const visibleKeys = ['$full_name', '$wedding_date'];
                $('#livePreviewVars').text(
                    visibleKeys
                        .filter(k => replacements[k] !== undefined)
                        .map(k => `${k} = ${replacements[k]}`)
                        .join(' | ')
                );
            }

            function stripHtml(html) {
                return $('<div>').html(html || '').text();
            }

            function loadTemplateIntoBuilder(tituloHtml, cuerpoHtml, despedidaHtml) {
                titleBlock = parseSingleTextBlock(tituloHtml);
                farewellBlock = parseSingleTextBlock(despedidaHtml);
                bodyBlocks = parseBodyBlocks(cuerpoHtml);
                if (!bodyBlocks.length) {
                    bodyBlocks = [createTextBlock({ content: '' })];
                }
                renderFixedBlock('#titleBlockContainer', titleBlock, 'Título del correo...', 'title');
                renderFixedBlock('#farewellBlockContainer', farewellBlock, 'Despedida del correo...', 'farewell');
                renderBodyBlocks();
            }

            function parseSingleTextBlock(html) {
                if (!html) return createTextBlock({ content: '' });

                const $tmp = $('<div>').html(html);
                const $firstEl = $tmp.children().first();
                if ($tmp.children().length === 1 && $firstEl.length && $firstEl.prop('tagName').toLowerCase() !== 'img') {
                    const style = extractTextStyleFromElement($firstEl[0]);
                    return createTextBlock({ content: $firstEl.html(), style: style });
                }
                return createTextBlock({ content: $tmp.html() });
            }

            function parseBodyBlocks(html) {
                if (!html) return [createTextBlock({ content: '' })];
                const blocks = [];
                const $tmp = $('<div>').html(html);

                $tmp.contents().each(function () {
                    if (this.nodeType === 3) {
                        const text = String(this.textContent || '').trim();
                        if (text) blocks.push(createTextBlock({ content: escapeHtml(text) }));
                        return;
                    }

                    if (this.nodeType !== 1) return;
                    const $node = $(this);
                    const tag = ($node.prop('tagName') || '').toLowerCase();

                    if (isSpacerLikeElement($node)) {
                        blocks.push(createSpacerBlock({ height: getSpacerHeightFromElement($node) }));
                        return;
                    }

                    if (tag === 'img') {
                        blocks.push(createImageBlock({
                            src: $node.attr('src') || '',
                            align: 'center',
                            widthPercent: getImageWidthPercent($node)
                        }));
                        return;
                    }

                    const $imgs = $node.find('img');
                    const ownText = ($node.clone().children('img').remove().end().text() || '').trim();
                    if ($imgs.length === 1 && !ownText) {
                        const $img = $imgs.first();
                        blocks.push(createImageBlock({
                            src: $img.attr('src') || '',
                            align: normalizeAlign($node.css('text-align') || $node.attr('align') || 'center'),
                            widthPercent: getImageWidthPercent($img)
                        }));
                        return;
                    }

                    blocks.push(createTextBlock({
                        content: $node.html(),
                        style: extractTextStyleFromElement(this)
                    }));
                });

                return blocks;
            }

            function isSpacerLikeElement($node) {
                if (!$node || !$node.length) return false;
                const tag = String($node.prop('tagName') || '').toLowerCase();
                if (!['p', 'div', 'span'].includes(tag)) return false;
                if ($node.find('img,figure,picture,table,ul,ol,li,blockquote').length) return false;

                const raw = ($node.html() || '').replace(/<!--.*?-->/g, '');
                const normalized = raw
                    .replace(/&nbsp;/gi, '')
                    .replace(/\u00a0/g, '')
                    .replace(/<br\s*\/?\s*>/gi, '')
                    .replace(/\s+/g, '')
                    .trim();

                return normalized === '';
            }

            function getSpacerHeightFromElement($node) {
                const style = String($node.attr('style') || '');
                const styleHeightMatch = style.match(/(?:^|;)\s*height\s*:\s*(\d+(?:\.\d+)?)px/i);
                if (styleHeightMatch) {
                    const val = Math.round(parseFloat(styleHeightMatch[1]));
                    return isNaN(val) ? DEFAULT_SPACER_HEIGHT : Math.max(4, Math.min(120, val));
                }

                const lineHeightMatch = style.match(/(?:^|;)\s*line-height\s*:\s*(\d+(?:\.\d+)?)px/i);
                if (lineHeightMatch) {
                    const val = Math.round(parseFloat(lineHeightMatch[1]));
                    return isNaN(val) ? DEFAULT_SPACER_HEIGHT : Math.max(4, Math.min(120, val));
                }

                return DEFAULT_SPACER_HEIGHT;
            }

            function getImageWidthPercent($img) {
                if (!$img || !$img.length) return 100;

                const style = String($img.attr('style') || '');
                const widthPctMatch = style.match(/(?:^|;)\s*width\s*:\s*(\d+(?:\.\d+)?)%/i);
                if (widthPctMatch) {
                    const val = Math.round(parseFloat(widthPctMatch[1]));
                    return isNaN(val) ? 100 : Math.max(20, Math.min(100, val));
                }

                return 100;
            }

            function extractTextStyleFromElement(el) {
                const style = Object.assign({}, DEFAULT_TEXT_STYLE);
                if (!el || !el.style) return style;
                if (el.style.fontSize) style.fontSize = el.style.fontSize;
                if (el.style.fontWeight) style.fontWeight = (String(el.style.fontWeight).toLowerCase() === '700' || String(el.style.fontWeight).toLowerCase() === 'bold') ? 'bold' : 'normal';
                if (el.style.color) style.color = toHexColor(el.style.color) || DEFAULT_TEXT_STYLE.color;
                if (el.style.textAlign) style.textAlign = normalizeAlign(el.style.textAlign);
                if (el.style.fontFamily) style.fontFamily = el.style.fontFamily;
                if (el.style.letterSpacing) style.letterSpacing = el.style.letterSpacing;
                if (el.style.lineHeight) style.lineHeight = el.style.lineHeight;
                return style;
            }

            function normalizeAlign(align) {
                const value = String(align || '').toLowerCase();
                if (value === 'left' || value === 'right' || value === 'center' || value === 'justify') return value;
                return 'left';
            }

            function toHexColor(color) {
                if (!color) return null;
                if (color.startsWith('#')) return color;
                const rgbMatch = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
                if (!rgbMatch) return null;
                const r = parseInt(rgbMatch[1], 10).toString(16).padStart(2, '0');
                const g = parseInt(rgbMatch[2], 10).toString(16).padStart(2, '0');
                const b = parseInt(rgbMatch[3], 10).toString(16).padStart(2, '0');
                return `#${r}${g}${b}`;
            }

            // Funciones de reemplazo y construcción de HTML
            function getSampleReplacements() {
                return {
                    '$full_name': 'María Pérez',
                    '$wedding_date': '12 de junio de 2026',
                    '$schedule_button': (function(){
                        const linkFormRegister = "https://www.citas.efegepho.com.mx/inquire-form.php";
                        const tablaOrigen = 'template_preview';
                        const id = '0';
                        const linkWithParams = linkFormRegister;
                        const buttonText = 'Agenda ahora';
                        const trackUrl = 'https://citas.efegepho.com.mx/pixel/click.php?id=' + encodeURIComponent(id) + '&tabla_origen=' + encodeURIComponent(tablaOrigen) + '&correo=1&url=' + encodeURIComponent(linkWithParams);
                        return "<a class='btn-agenda' href='" + trackUrl + "' style='display: block; margin: 40px auto 20px auto; padding: 16px 24px; background-color: #eee8dc; border: 1.5px solid #3B3B3B; border-radius: 15px; color: #3B3B3B; text-align: center; text-decoration: none; font-weight: 600; font-family: \"Open Sans\", sans-serif; box-sizing: border-box; cursor: pointer;' role='button'>" + buttonText + "</a>";
                    })()
                };
            }

            function replacePlaceholders(text, replacements) {
                if (!text) return '';
                let out = String(text);
                for (const key in replacements) {
                    if (!Object.prototype.hasOwnProperty.call(replacements, key)) continue;
                    const val = replacements[key];
                    if (key === '$schedule_button') {
                        out = out.split(key).join(val);
                    } else {
                        out = out.split(key).join(escapeHtml(val));
                    }
                }
                return out;
            }

            function escapeHtml(text) {
                if (!text) return '';
                return String(text)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function buildEmailHtml({ asunto, titulo, cuerpo, despedida, tablaOrigen = '', leadId = '', templateId = null }) {
                const templatePart = templateId ? `&template_id=${encodeURIComponent(templateId)}` : '';
                return `
<html>
<head>
  <meta charset="utf-8">
  <title>${escapeHtml(asunto)}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Open+Sans&display=swap');
    body { margin:0; padding:0; }
    .bg{ width:96%; margin:0 auto; padding:50px 0; background-color:#e8e8e8; }
    p { margin:15px; }
    .container { width:90%; max-width:600px; margin:0 auto; border-radius:30px; background-color:#fff; line-height:1.5; font-size:1.5rem; box-shadow:0px 4px 6px rgba(0,0,0,0.1); font-family: 'Open Sans', sans-serif; }
    .card-container { padding:10px 20px; margin:10px; overflow:hidden; }
    .card-container img { max-width:100%; height:auto; display:block; }
    .image-size-small,
    .image-size-small img { width:25%; height:auto; }
    .image-size-medium,
    .image-size-medium img { width:50%; height:auto; }
    .image-size-large,
    .image-size-large img { width:75%; height:auto; }
    .btn-agenda { font-size:1.5rem; width:100%; }
    .header { text-align:left; padding:10px 30px; font-size:1.5rem; background-color:#eee8dc; color:#3B3B3B; font-weight:600; margin-top:13px; }
    .content { padding:20px 0 0 0; margin:0; }
    .logo { width:120px; margin:0 auto; display:block; }
    @media screen and (min-width:768px) {
      .container { max-width:750px; font-size:1.5rem; }
      .header { font-size:1.7rem; padding:10px 50px; }
      .card-container { padding:10px 30px; }
      .logo { width:140px; }
      .btn-agenda { font-size:1.7rem; width:50%; }
    }
  </style>
</head>
<body>
  <div class='bg'>
    <div class='container'>
      <div class='content'>
        <div style='text-align:center;'>
          <img class='logo' alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>
        </div>
        <div class='header'>${titulo}</div>
        <div class='card-container'>
          ${cuerpo}
          ${despedida ? despedida : ''}
        </div>
      </div>
    </div>
    <div style='text-align:center; margin-top:20px;'>
      <img class='logo' alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>
    </div>
    <img src='https://citas.efegepho.com.mx/pixel/open.php?id=${encodeURIComponent(leadId)}&tabla_origen=${encodeURIComponent(tablaOrigen)}&correo=1${templatePart}' width='1' height='1' style='display:none' />
  </div>
</body>
</html>
`;
            }
        });
    </script>
</body>

</html>
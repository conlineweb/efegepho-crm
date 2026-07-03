<style>
    .admin-site-footer {
        margin-top: auto;
        padding: 0 1.75rem 1.5rem;
        font-family: 'DM Sans', system-ui, -apple-system, sans-serif;
    }

    .admin-site-footer__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.875rem 1.25rem;
        border: 1px solid #e8e5df;
        border-radius: 12px;
        background: linear-gradient(180deg, #faf9f7 0%, #f5f3ef 100%);
        box-shadow: 0 1px 2px rgba(44, 44, 42, 0.04);
    }

    .admin-site-footer__left,
    .admin-site-footer__right {
        display: flex;
        align-items: center;
        min-width: 0;
    }

    .admin-site-footer__brand {
        margin: 0;
        font-size: 0.8125rem;
        font-weight: 500;
        letter-spacing: 0.01em;
        color: #2c2c2a;
        line-height: 1.4;
    }

    .admin-site-footer__brand strong {
        font-weight: 600;
        color: #b0876a;
    }

    .admin-site-footer__divider {
        flex-shrink: 0;
        width: 1px;
        height: 1.25rem;
        background: #e8e5df;
    }

    .admin-site-footer__tagline {
        margin: 0;
        font-size: 0.8125rem;
        font-weight: 400;
        font-style: italic;
        letter-spacing: 0.02em;
        color: #888780;
        line-height: 1.4;
        text-align: right;
    }

    .admin-site-footer__tagline::before {
        content: '✦';
        display: inline-block;
        margin-right: 0.45rem;
        font-style: normal;
        font-size: 0.65rem;
        color: #b0876a;
        vertical-align: middle;
        opacity: 0.85;
    }

    [data-bs-theme="dark"] .admin-site-footer__inner {
        border-color: rgba(255, 255, 255, 0.08);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.04) 0%, rgba(255, 255, 255, 0.02) 100%);
        box-shadow: none;
    }

    [data-bs-theme="dark"] .admin-site-footer__brand {
        color: #e8e6e1;
    }

    [data-bs-theme="dark"] .admin-site-footer__brand strong {
        color: #c9a882;
    }

    [data-bs-theme="dark"] .admin-site-footer__divider {
        background: rgba(255, 255, 255, 0.1);
    }

    [data-bs-theme="dark"] .admin-site-footer__tagline {
        color: #a8a59e;
    }

    [data-bs-theme="dark"] .admin-site-footer__tagline::before {
        color: #c9a882;
    }

    @media (max-width: 575.98px) {
        .admin-site-footer {
            padding: 0 1rem 1.25rem;
        }

        .admin-site-footer__inner {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.625rem;
            padding: 0.875rem 1rem;
        }

        .admin-site-footer__divider {
            display: none;
        }

        .admin-site-footer__tagline {
            text-align: left;
        }
    }
</style>

<footer class="admin-site-footer">
    <div class="admin-site-footer__inner">
        <div class="admin-site-footer__left">
            <p class="admin-site-footer__brand">2026 &copy; <strong>Efegepho.</strong></p>
        </div>
        <div class="admin-site-footer__divider" aria-hidden="true"></div>
        <div class="admin-site-footer__right">
            <p class="admin-site-footer__tagline">Moments worth remembering.</p>
        </div>
    </div>
</footer>
        </div>
    </div>
   

        </div>
    </div>
    <script src="assets/static/js/components/dark.js"></script>
    <script src="assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/compiled/js/app.js"></script>
<script src="assets/extensions/simple-datatables/umd/simple-datatables.js"></script>
<script src="assets/static/js/pages/simple-datatables.js"></script>
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- jQuery (necesario para DataTables) -->
  <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
  <!-- DataTables JS -->
  <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
  
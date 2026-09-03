        </main>
    </div>
</div>

<!-- GPS Location Interactive Map Modal -->
<div class="modal-backdrop" id="gpsLocationModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="gpsModalTitle">Lokasi GPS Siswa</h3>
            <button type="button" class="modal-close" onclick="closeAllModals()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <div style="padding: 12px 20px; background: rgba(0,0,0,0.2); font-size: 0.85rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center;">
                <span id="gpsCoordsText">Memuat titik koordinat...</span>
                <a href="#" id="gpsExternalLink" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 4px 8px;">
                    Buka Google Maps &#x2197;
                </a>
            </div>
            <iframe id="gpsMapIframe" src="about:blank" width="100%" height="320" style="border: none; display: block;" loading="lazy"></iframe>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAllModals()">Tutup</button>
        </div>
    </div>
</div>

<script src="assets/js/admin.js"></script>
</body>
</html>

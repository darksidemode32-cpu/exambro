package com.exambro.app;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.ActivityManager;
import android.app.AlertDialog;
import android.content.Context;
import android.content.pm.PackageManager;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.KeyEvent;
import android.view.View;
import android.view.Window;
import android.view.WindowManager;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;

/**
 * Exambro Kiosk Lock Activity
 * Implements Android LockTaskMode, Remote Brightness, GPS tracking,
 * and 5-minute dynamic exit token validation according to PRD.
 */
public class MainActivity extends AppCompatActivity {

    // Default Master Server URL (can be customized by School Code or QR Code)
    private static final String DEFAULT_MASTER_URL = "http://10.0.2.2:5000/exambro"; 

    private WebView webView;
    private ProgressBar progressBar;
    private TextView txtSchoolTitle;
    private Button btnExit;

    private String currentSchoolCode = "SMAN1";
    private String currentSessionToken = "";
    private String examServerUrl = "";
    private int currentBrightness = 80;

    private Handler handler = new Handler(Looper.getMainLooper());
    private Runnable heartbeatRunnable;

    private double currentLatitude = 0.0;
    private double currentLongitude = 0.0;
    private float locationAccuracy = 0.0f;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Enforce Fullscreen & Keep Screen On
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        getWindow().setFlags(
                WindowManager.LayoutParams.FLAG_FULLSCREEN | WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON,
                WindowManager.LayoutParams.FLAG_FULLSCREEN | WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON
        );

        // Hide Navigation & Status Bars (Immersive Sticky Mode)
        hideSystemUI();

        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webView);
        progressBar = findViewById(R.id.progressBar);
        txtSchoolTitle = findViewById(R.id.txtSchoolTitle);
        btnExit = findViewById(R.id.btnExit);

        setupWebView();
        requestGpsPermission();

        btnExit.setOnClickListener(v -> showExitPasswordDialog());

        // Prompt for School Code or initialize default
        showSchoolCodeDialog();
    }

    @Override
    protected void onResume() {
        super.onResume();
        hideSystemUI();
        startKioskMode();
    }

    /**
     * Lock Task Mode (Screen Pinning / Kiosk Mode Native Android)
     */
    private void startKioskMode() {
        try {
            ActivityManager am = (ActivityManager) getSystemService(Context.ACTIVITY_SERVICE);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                if (am.getLockTaskModeState() == ActivityManager.LOCK_TASK_MODE_NONE) {
                    startLockTask();
                }
            } else {
                startLockTask();
            }
        } catch (Exception e) {
            // Ignored if device policy is not device-owner
        }
    }

    /**
     * Hide Navigation Bar, Home, Recent Apps (Immersive Sticky)
     */
    private void hideSystemUI() {
        View decorView = getWindow().getDecorView();
        decorView.setSystemUiVisibility(
                View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                        | View.SYSTEM_UI_FLAG_LAYOUT_STABLE
                        | View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
                        | View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
                        | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
                        | View.SYSTEM_UI_FLAG_FULLSCREEN
        );
    }

    @Override
    public void onWindowFocusChanged(boolean hasFocus) {
        super.onWindowFocusChanged(hasFocus);
        if (hasFocus) {
            hideSystemUI();
        } else {
            // Student tried to pull down notification bar or switch window -> report violation
            reportViolation("focus_lost", "Aplikasi kehilangan fokus atau notifikasi ditarik.");
        }
    }

    /**
     * Block Back Key & Recent Apps
     */
    @Override
    public void onBackPressed() {
        Toast.makeText(this, "Tombol Kembali dinonaktifkan! Tekan Keluar Ujian untuk meminta token.", Toast.LENGTH_SHORT).show();
        reportViolation("back_press", "Percobaan menekan tombol Back fisik.");
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if (keyCode == KeyEvent.KEYCODE_HOME || keyCode == KeyEvent.KEYCODE_APP_SWITCH) {
            reportViolation("home_recent_press", "Percobaan menekan tombol Home atau Recent Apps.");
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setLoadsImagesAutomatically(true);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        settings.setBuiltInZoomControls(false);

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                progressBar.setVisibility(View.GONE);
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                if (newProgress < 100) {
                    progressBar.setVisibility(View.VISIBLE);
                } else {
                    progressBar.setVisibility(View.GONE);
                }
            }
        });
    }

    /**
     * Remote Screen Brightness Override (PRD Requirement 4.1)
     */
    public void setDeviceBrightness(int brightnessPercent) {
        currentBrightness = Math.max(10, Math.min(100, brightnessPercent));
        runOnUiThread(() -> {
            WindowManager.LayoutParams layoutParams = getWindow().getAttributes();
            layoutParams.screenBrightness = currentBrightness / 100.0f;
            getWindow().setAttributes(layoutParams);
        });
    }

    /**
     * GPS Location Tracker (PRD Requirement 4.2)
     */
    private void requestGpsPermission() {
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this, new String[]{
                    Manifest.permission.ACCESS_FINE_LOCATION,
                    Manifest.permission.ACCESS_COARSE_LOCATION
            }, 101);
        } else {
            startLocationUpdates();
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == 101 && grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
            startLocationUpdates();
        }
    }

    private void startLocationUpdates() {
        try {
            LocationManager locationManager = (LocationManager) getSystemService(Context.LOCATION_SERVICE);
            if (locationManager != null && ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED) {
                Location lastLoc = locationManager.getLastKnownLocation(LocationManager.GPS_PROVIDER);
                if (lastLoc != null) {
                    currentLatitude = lastLoc.getLatitude();
                    currentLongitude = lastLoc.getLongitude();
                    locationAccuracy = lastLoc.getAccuracy();
                }

                locationManager.requestLocationUpdates(LocationManager.GPS_PROVIDER, 10000, 10, new LocationListener() {
                    @Override
                    public void onLocationChanged(@NonNull Location location) {
                        currentLatitude = location.getLatitude();
                        currentLongitude = location.getLongitude();
                        locationAccuracy = location.getAccuracy();
                    }
                });
            }
        } catch (Exception e) {
            // Location provider unavailable
        }
    }

    /**
     * School Code Dialog (PRD 2: Dynamic Endpoint Routing)
     */
    private void showSchoolCodeDialog() {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setTitle(R.string.school_code_prompt);
        builder.setCancelable(false);

        final EditText input = new EditText(this);
        input.setHint("Contoh: SMAN1 atau SMKN2");
        input.setText("SMAN1");
        builder.setView(input);

        builder.setPositiveButton("Hubungkan", (dialog, which) -> {
            String code = input.getText().toString().trim().toUpperCase();
            if (!code.isEmpty()) {
                currentSchoolCode = code;
                syncSchoolConfig(code);
            }
        });

        builder.show();
    }

    /**
     * Sync School Configuration & Register Student Session
     */
    private void syncSchoolConfig(String schoolCode) {
        progressBar.setVisibility(View.VISIBLE);
        new Thread(() -> {
            try {
                // 1. Fetch School config
                String endpoint = DEFAULT_MASTER_URL + "/api/school.php?code=" + schoolCode;
                URL url = new URL(endpoint);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("GET");
                conn.setConnectTimeout(6000);

                if (conn.getResponseCode() == 200) {
                    BufferedReader br = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                    StringBuilder sb = new StringBuilder();
                    String line;
                    while ((line = br.readLine()) != null) sb.append(line);
                    br.close();

                    JSONObject res = new JSONObject(sb.toString());
                    if ("success".equals(res.optString("status"))) {
                        JSONObject data = res.getJSONObject("data");
                        examServerUrl = data.getString("exam_url");
                        int brightness = data.optInt("remote_brightness", 80);
                        String schoolName = data.getString("school_name");

                        setDeviceBrightness(brightness);

                        // 2. Register Student Session
                        registerStudentSession(schoolCode, schoolName);
                    }
                } else {
                    runOnUiThread(() -> {
                        progressBar.setVisibility(View.GONE);
                        Toast.makeText(this, "Kode Sekolah tidak valid!", Toast.LENGTH_LONG).show();
                        showSchoolCodeDialog();
                    });
                }
            } catch (Exception e) {
                runOnUiThread(() -> {
                    progressBar.setVisibility(View.GONE);
                    Toast.makeText(this, "Gagal koneksi ke server: " + e.getMessage(), Toast.LENGTH_LONG).show();
                });
            }
        }).start();
    }

    private void registerStudentSession(String schoolCode, String schoolName) {
        new Thread(() -> {
            try {
                URL url = new URL(DEFAULT_MASTER_URL + "/api/student/login.php");
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setDoOutput(true);

                JSONObject payload = new JSONObject();
                payload.put("school_code", schoolCode);
                payload.put("student_name", "Siswa Android (" + Build.MODEL + ")");
                payload.put("device_brand", Build.MANUFACTURER);
                payload.put("device_model", Build.MODEL);
                payload.put("device_os", "Android " + Build.VERSION.RELEASE);
                payload.put("latitude", currentLatitude);
                payload.put("longitude", currentLongitude);
                payload.put("location_accuracy", locationAccuracy);

                OutputStream os = conn.getOutputStream();
                os.write(payload.toString().getBytes());
                os.flush();
                os.close();

                if (conn.getResponseCode() == 200) {
                    BufferedReader br = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                    StringBuilder sb = new StringBuilder();
                    String line;
                    while ((line = br.readLine()) != null) sb.append(line);
                    br.close();

                    JSONObject json = new JSONObject(sb.toString());
                    currentSessionToken = json.getJSONObject("data").getString("session_token");

                    runOnUiThread(() -> {
                        txtSchoolTitle.setText(schoolName);
                        webView.loadUrl(examServerUrl);
                        startHeartbeat();
                    });
                }
            } catch (Exception e) {
                e.printStackTrace();
            }
        }).start();
    }

    /**
     * Heartbeat Sync (Checks Remote Brightness & Admin Force Exit)
     */
    private void startHeartbeat() {
        heartbeatRunnable = new Runnable() {
            @Override
            public void run() {
                new Thread(() -> {
                    try {
                        URL url = new URL(DEFAULT_MASTER_URL + "/api/student/heartbeat.php");
                        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                        conn.setRequestMethod("POST");
                        conn.setRequestProperty("Content-Type", "application/json");
                        conn.setDoOutput(true);

                        JSONObject payload = new JSONObject();
                        payload.put("session_token", currentSessionToken);
                        payload.put("battery_level", 90);

                        OutputStream os = conn.getOutputStream();
                        os.write(payload.toString().getBytes());
                        os.flush();
                        os.close();

                        if (conn.getResponseCode() == 200) {
                            BufferedReader br = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                            StringBuilder sb = new StringBuilder();
                            String line;
                            while ((line = br.readLine()) != null) sb.append(line);
                            br.close();

                            JSONObject res = new JSONObject(sb.toString());
                            JSONObject cmds = res.optJSONObject("commands");
                            if (cmds != null) {
                                int newBright = cmds.optInt("remote_brightness", currentBrightness);
                                if (newBright != currentBrightness) {
                                    setDeviceBrightness(newBright);
                                }
                                if (cmds.optBoolean("force_exit", false)) {
                                    runOnUiThread(() -> {
                                        Toast.makeText(MainActivity.this, "Pengawas mengeluarkan Anda dari ujian.", Toast.LENGTH_LONG).show();
                                        finishAffinity();
                                    });
                                }
                            }
                        }
                    } catch (Exception ignored) {}
                }).start();

                handler.postDelayed(this, 4000);
            }
        };
        handler.postDelayed(heartbeatRunnable, 4000);
    }

    /**
     * Exit Password Dialog with 5-Minute Dynamic Token (PRD Requirement 3.2)
     */
    private void showExitPasswordDialog() {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setTitle(R.string.exit_password_title);

        final EditText input = new EditText(this);
        input.setHint(R.string.exit_password_hint);
        input.setTextAlignment(View.TEXT_ALIGNMENT_CENTER);
        builder.setView(input);

        builder.setPositiveButton("Buka Kunci & Keluar", (dialog, which) -> {
            String token = input.getText().toString().trim().toUpperCase();
            verifyExitPassword(token);
        });
        builder.setNegativeButton("Batal", (dialog, which) -> dialog.dismiss());
        builder.show();
    }

    private void verifyExitPassword(String token) {
        progressBar.setVisibility(View.VISIBLE);
        new Thread(() -> {
            try {
                URL url = new URL(DEFAULT_MASTER_URL + "/api/student/verify_exit.php");
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setDoOutput(true);

                JSONObject payload = new JSONObject();
                payload.put("session_token", currentSessionToken);
                payload.put("exit_password", token);

                OutputStream os = conn.getOutputStream();
                os.write(payload.toString().getBytes());
                os.flush();
                os.close();

                int code = conn.getResponseCode();
                BufferedReader br = new BufferedReader(new InputStreamReader(code == 200 ? conn.getInputStream() : conn.getErrorStream()));
                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = br.readLine()) != null) sb.append(line);
                br.close();

                JSONObject res = new JSONObject(sb.toString());
                boolean valid = res.optBoolean("valid", false);
                String msg = res.optString("message", "Token tidak valid.");

                runOnUiThread(() -> {
                    progressBar.setVisibility(View.GONE);
                    if (valid) {
                        Toast.makeText(this, "Ujian Selesai! Kunci dibuka.", Toast.LENGTH_SHORT).show();
                        try {
                            stopLockTask();
                        } catch (Exception ignored) {}
                        finish();
                    } else {
                        // Show warning dialog (e.g. expired > 5 minutes)
                        new AlertDialog.Builder(this)
                                .setTitle("Gagal Keluar")
                                .setMessage(msg)
                                .setPositiveButton("OK", null)
                                .show();
                    }
                });

            } catch (Exception e) {
                runOnUiThread(() -> {
                    progressBar.setVisibility(View.GONE);
                    Toast.makeText(this, "Kesalahan verifikasi: " + e.getMessage(), Toast.LENGTH_SHORT).show();
                });
            }
        }).start();
    }

    private void reportViolation(String type, String desc) {
        new Thread(() -> {
            try {
                URL url = new URL(DEFAULT_MASTER_URL + "/api/student/violation.php");
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setDoOutput(true);

                JSONObject payload = new JSONObject();
                payload.put("session_token", currentSessionToken);
                payload.put("violation_type", type);
                payload.put("description", desc);

                OutputStream os = conn.getOutputStream();
                os.write(payload.toString().getBytes());
                os.flush();
                os.close();
                conn.getResponseCode();
            } catch (Exception ignored) {}
        }).start();
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (heartbeatRunnable != null) {
            handler.removeCallbacks(heartbeatRunnable);
        }
    }
}

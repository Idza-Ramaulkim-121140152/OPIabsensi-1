<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face API Tester</title>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --card: #ffffff;
            --line: #d8dfec;
            --text: #202733;
            --muted: #5f6f8c;
            --primary: #0a5fe8;
            --primary-dark: #0849b2;
            --danger: #cf1f2e;
            --success-bg: #e9f8ef;
            --success-text: #156d39;
            --warn-bg: #fff3e7;
            --warn-text: #8b4f11;
            --shadow: 0 14px 40px rgba(16, 36, 74, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background: linear-gradient(160deg, #f8fbff 0%, #eef4ff 55%, #f4f6fc 100%);
        }

        .container {
            width: min(1180px, 96%);
            margin: 24px auto 40px;
        }

        .header {
            margin-bottom: 20px;
            padding: 16px 20px;
            border-radius: 14px;
            background: #0f233f;
            color: #e7efff;
            box-shadow: var(--shadow);
        }

        .header h1 {
            margin: 0 0 6px;
            font-size: 24px;
            letter-spacing: 0.2px;
        }

        .header p {
            margin: 0;
            font-size: 14px;
            color: #c5d5f8;
        }

        .top-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 10px 28px rgba(28, 46, 77, 0.07);
        }

        .card h2 {
            margin: 0 0 12px;
            font-size: 18px;
        }

        .field {
            margin-bottom: 10px;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            color: var(--muted);
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            border: 1px solid #c8d3e9;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
            color: var(--text);
            background: #fff;
            outline: none;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: #8bb2ff;
            box-shadow: 0 0 0 3px rgba(46, 110, 235, 0.15);
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        button {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        button.primary {
            background: var(--primary);
            color: #fff;
        }

        button.primary:hover {
            background: var(--primary-dark);
        }

        button.secondary {
            background: #dce7ff;
            color: #17325e;
        }

        button.secondary:hover {
            background: #c8d9ff;
        }

        button:disabled {
            cursor: not-allowed;
            opacity: 0.68;
        }

        .notice {
            margin-top: 10px;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
        }

        .notice.success {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .notice.warn {
            background: var(--warn-bg);
            color: var(--warn-text);
        }

        .json {
            margin-top: 10px;
            background: #0f1625;
            color: #d2e3ff;
            border-radius: 10px;
            padding: 10px;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 260px;
            overflow: auto;
        }

        .table-wrap {
            overflow: auto;
            border: 1px solid #d9e2f2;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
            background: #fff;
        }

        th,
        td {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid #e6edf9;
            font-size: 13px;
        }

        th {
            background: #f3f7ff;
            color: #334568;
            font-weight: 600;
        }

        .status {
            display: inline-flex;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        .status.matched {
            background: #ddf7e7;
            color: #0d7d3f;
        }

        .status.unknown {
            background: #ffe9d9;
            color: #8a460b;
        }

        .meta {
            margin-top: 8px;
            color: #60749b;
            font-size: 12px;
        }

        .camera-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 12px;
            margin-bottom: 8px;
        }

        .camera-box {
            border: 1px solid #d8e2f3;
            border-radius: 12px;
            background: #0d172b;
            overflow: hidden;
            min-height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .camera-box video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .preview-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .preview-item {
            border: 1px solid #d8e2f3;
            border-radius: 10px;
            padding: 8px;
            background: #f8fbff;
        }

        .preview-item h3 {
            margin: 0 0 6px;
            font-size: 12px;
            color: #39507f;
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        .preview-item img {
            width: 100%;
            border-radius: 8px;
            border: 1px solid #cfdcf5;
            display: block;
        }

        .preview-placeholder {
            border: 1px dashed #b7c9ea;
            border-radius: 8px;
            color: #6c7fa7;
            font-size: 12px;
            min-height: 84px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 8px;
            background: #edf3ff;
        }

        @media (max-width: 960px) {
            .camera-layout,
            .top-grid,
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
@verbatim
<div id="app" class="container">
    <section class="header">
        <h1>Face Recognition API Tester (Vue)</h1>
        <p>Gunakan halaman ini untuk uji register wajah, absensi, dan monitoring riwayat absensi.</p>
    </section>

    <section class="card" style="margin-bottom: 14px;">
        <h2>Kamera Langsung</h2>
        <div class="camera-layout">
            <div class="camera-box">
                <video ref="cameraVideo" autoplay muted playsinline></video>
                <canvas ref="captureCanvas" style="display:none;"></canvas>
            </div>
            <div class="preview-grid">
                <div class="preview-item">
                    <h3>Preview Register</h3>
                    <img v-if="capturedRegisterPreview" :src="capturedRegisterPreview" alt="Register preview">
                    <div v-else class="preview-placeholder">Belum ada capture register.</div>
                </div>
                <div class="preview-item">
                    <h3>Preview Attendance</h3>
                    <img v-if="capturedAttendancePreview" :src="capturedAttendancePreview" alt="Attendance preview">
                    <div v-else class="preview-placeholder">Belum ada capture attendance.</div>
                </div>
            </div>
        </div>
        <div class="actions">
            <button class="secondary" @click="startCamera" :disabled="cameraReady">Nyalakan Kamera</button>
            <button class="secondary" @click="stopCamera" :disabled="!cameraReady">Matikan Kamera</button>
            <button class="primary" @click="captureForRegister" :disabled="!cameraReady">Capture Register</button>
            <button class="primary" @click="captureForAttendance" :disabled="!cameraReady">Capture Attendance</button>
        </div>
        <div class="notice warn" v-if="cameraError">{{ cameraError }}</div>
    </section>

    <section class="top-grid">
        <article class="card">
            <h2>Konfigurasi API</h2>
            <div class="field">
                <label>Base URL Laravel API</label>
                <input v-model.trim="apiBaseUrl" placeholder="http://127.0.0.1:8000">
            </div>
            <div class="field">
                <label>Bearer Token</label>
                <input v-model.trim="bearerToken" placeholder="replace-with-secure-token">
            </div>
            <div class="actions">
                <button class="secondary" @click="saveToken">Simpan Token</button>
                <button class="secondary" @click="loadAttendanceHistory" :disabled="historyLoading">
                    {{ historyLoading ? 'Memuat...' : 'Refresh Riwayat' }}
                </button>
            </div>
            <div class="notice warn" v-if="globalError">{{ globalError }}</div>
        </article>

        <article class="card">
            <h2>Ringkasan Terakhir</h2>
            <div class="meta">
                Base URL aktif: <strong>{{ apiBaseUrl }}</strong>
            </div>
            <div class="meta">
                Total log saat ini: <strong>{{ historyMeta.total ?? 0 }}</strong>
            </div>
            <div class="meta">
                Last page: <strong>{{ historyMeta.last_page ?? 1 }}</strong>
            </div>
            <div class="json" v-if="lastActionResult">{{ formatJson(lastActionResult) }}</div>
        </article>
    </section>

    <section class="grid">
        <article class="card">
            <h2>Registrasi Wajah</h2>
            <div class="field">
                <label>Employee ID</label>
                <input v-model.number="registerForm.employee_id" type="number" min="1" placeholder="101">
            </div>
            <div class="field">
                <label>Nama Pegawai</label>
                <input v-model.trim="registerForm.name" placeholder="Andi">
            </div>
            <div class="meta">Gunakan tombol <strong>Capture Register</strong> pada panel kamera.</div>
            <div class="actions">
                <button class="primary" @click="registerFace" :disabled="registerLoading">
                    {{ registerLoading ? 'Mengirim...' : 'Register Wajah' }}
                </button>
            </div>
            <div class="notice success" v-if="registerMessage">{{ registerMessage }}</div>
            <div class="json" v-if="registerResult">{{ formatJson(registerResult) }}</div>
        </article>

        <article class="card">
            <h2>Absensi Wajah</h2>
            <div class="meta">Gunakan tombol <strong>Capture Attendance</strong> pada panel kamera.</div>
            <div class="actions">
                <button class="primary" @click="submitAttendance" :disabled="attendanceLoading">
                    {{ attendanceLoading ? 'Mengirim...' : 'Kirim Absensi' }}
                </button>
            </div>
            <div class="notice success" v-if="attendanceMessage">{{ attendanceMessage }}</div>
            <div class="json" v-if="attendanceResult">{{ formatJson(attendanceResult) }}</div>
        </article>
    </section>

    <section class="card" style="margin-top: 14px;">
        <h2>Riwayat Absensi</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID Log</th>
                        <th>Timestamp</th>
                        <th>Status</th>
                        <th>Confidence</th>
                        <th>Employee ID</th>
                        <th>Nama</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="historyItems.length === 0">
                        <td colspan="6">Belum ada data riwayat.</td>
                    </tr>
                    <tr v-for="item in historyItems" :key="item.id">
                        <td>{{ item.id }}</td>
                        <td>{{ item.timestamp }}</td>
                        <td>
                            <span class="status" :class="item.status">{{ item.status }}</span>
                        </td>
                        <td>{{ item.confidence }}</td>
                        <td>{{ item.employee_id ?? '-' }}</td>
                        <td>{{ item.employee?.name ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="meta">
            Page {{ historyMeta.current_page ?? 1 }} / {{ historyMeta.last_page ?? 1 }} |
            Total {{ historyMeta.total ?? 0 }} records
        </div>
    </section>
</div>

<script>
    const { createApp } = Vue;

    createApp({
        data() {
            return {
                apiBaseUrl: window.location.origin,
                bearerToken: localStorage.getItem('face_tester_token') ?? 'replace-with-secure-token',
                globalError: '',
                cameraStream: null,
                cameraReady: false,
                cameraError: '',
                capturedRegisterPreview: '',
                capturedAttendancePreview: '',

                registerForm: {
                    employee_id: null,
                    name: '',
                    image: null,
                },
                attendanceImage: null,

                registerLoading: false,
                attendanceLoading: false,
                historyLoading: false,

                registerMessage: '',
                attendanceMessage: '',
                registerResult: null,
                attendanceResult: null,
                lastActionResult: null,

                historyItems: [],
                historyMeta: {},
            };
        },
        mounted() {
            this.loadAttendanceHistory();
            this.startCamera();
        },
        beforeUnmount() {
            this.stopCamera();
        },
        methods: {
            saveToken() {
                localStorage.setItem('face_tester_token', this.bearerToken);
            },

            async startCamera() {
                this.cameraError = '';

                if (this.cameraReady && this.cameraStream) {
                    return;
                }

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    this.cameraError = 'Browser tidak mendukung akses kamera.';
                    return;
                }

                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'user',
                            width: { ideal: 1280 },
                            height: { ideal: 720 },
                        },
                        audio: false,
                    });

                    this.cameraStream = stream;

                    const video = this.$refs.cameraVideo;
                    video.srcObject = stream;
                    await video.play();
                    this.cameraReady = true;
                } catch (error) {
                    this.cameraReady = false;
                    this.cameraError = 'Tidak bisa mengakses kamera. Pastikan izin kamera diberikan di browser.';
                }
            },

            stopCamera() {
                if (this.cameraStream) {
                    this.cameraStream.getTracks().forEach((track) => track.stop());
                    this.cameraStream = null;
                }

                const video = this.$refs.cameraVideo;
                if (video) {
                    video.srcObject = null;
                }

                this.cameraReady = false;
            },

            async captureFrame() {
                if (!this.cameraReady) {
                    throw new Error('Kamera belum aktif. Klik "Nyalakan Kamera" dulu.');
                }

                const video = this.$refs.cameraVideo;
                const canvas = this.$refs.captureCanvas;

                const width = video.videoWidth || 1280;
                const height = video.videoHeight || 720;

                canvas.width = width;
                canvas.height = height;

                const context = canvas.getContext('2d');
                context.drawImage(video, 0, 0, width, height);

                const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
                const blob = await new Promise((resolve) => {
                    canvas.toBlob(resolve, 'image/jpeg', 0.92);
                });

                if (!blob) {
                    throw new Error('Gagal menangkap gambar dari kamera.');
                }

                return { blob, dataUrl };
            },

            async captureForRegister() {
                this.globalError = '';
                this.cameraError = '';

                try {
                    const { blob, dataUrl } = await this.captureFrame();
                    this.registerForm.image = blob;
                    this.capturedRegisterPreview = dataUrl;
                } catch (error) {
                    this.globalError = error.message;
                }
            },

            async captureForAttendance() {
                this.globalError = '';
                this.cameraError = '';

                try {
                    const { blob, dataUrl } = await this.captureFrame();
                    this.attendanceImage = blob;
                    this.capturedAttendancePreview = dataUrl;
                } catch (error) {
                    this.globalError = error.message;
                }
            },

            authHeaders() {
                if (!this.bearerToken) {
                    throw new Error('Bearer token wajib diisi.');
                }

                return {
                    'Authorization': `Bearer ${this.bearerToken}`,
                    'Accept': 'application/json',
                };
            },

            async parseResponse(response) {
                const raw = await response.text();
                try {
                    return JSON.parse(raw);
                } catch (_) {
                    return { message: raw };
                }
            },

            async registerFace() {
                this.globalError = '';
                this.registerMessage = '';
                this.registerResult = null;

                if (!this.registerForm.employee_id || !this.registerForm.name || !this.registerForm.image) {
                    this.globalError = 'Lengkapi employee_id, name, lalu capture wajah untuk register.';
                    return;
                }

                this.registerLoading = true;
                try {
                    const formData = new FormData();
                    formData.append('employee_id', String(this.registerForm.employee_id));
                    formData.append('name', this.registerForm.name);
                    formData.append('image', this.registerForm.image, 'register-capture.jpg');

                    const response = await fetch(`${this.apiBaseUrl}/api/face/register`, {
                        method: 'POST',
                        headers: this.authHeaders(),
                        body: formData,
                    });

                    const payload = await this.parseResponse(response);
                    if (!response.ok) {
                        throw new Error(payload.message ?? 'Register gagal.');
                    }

                    this.registerMessage = payload.message ?? 'Registrasi berhasil.';
                    this.registerResult = payload;
                    this.lastActionResult = payload;
                    await this.loadAttendanceHistory();
                } catch (error) {
                    this.globalError = error.message;
                } finally {
                    this.registerLoading = false;
                }
            },

            async submitAttendance() {
                this.globalError = '';
                this.attendanceMessage = '';
                this.attendanceResult = null;

                if (!this.attendanceImage) {
                    this.globalError = 'Capture wajah dulu untuk absensi.';
                    return;
                }

                this.attendanceLoading = true;
                try {
                    const formData = new FormData();
                    formData.append('image', this.attendanceImage, 'attendance-capture.jpg');

                    const response = await fetch(`${this.apiBaseUrl}/api/face/attendance`, {
                        method: 'POST',
                        headers: this.authHeaders(),
                        body: formData,
                    });

                    const payload = await this.parseResponse(response);
                    if (!response.ok) {
                        throw new Error(payload.message ?? 'Absensi gagal.');
                    }

                    this.attendanceMessage = payload.message ?? 'Absensi berhasil.';
                    this.attendanceResult = payload;
                    this.lastActionResult = payload;
                    await this.loadAttendanceHistory();
                } catch (error) {
                    this.globalError = error.message;
                } finally {
                    this.attendanceLoading = false;
                }
            },

            async loadAttendanceHistory() {
                this.globalError = '';
                this.historyLoading = true;
                try {
                    const response = await fetch(`${this.apiBaseUrl}/api/attendance`, {
                        method: 'GET',
                        headers: this.authHeaders(),
                    });

                    const payload = await this.parseResponse(response);
                    if (!response.ok) {
                        throw new Error(payload.message ?? 'Gagal mengambil riwayat.');
                    }

                    this.historyItems = payload.data ?? [];
                    this.historyMeta = payload.meta ?? {};
                } catch (error) {
                    this.globalError = error.message;
                } finally {
                    this.historyLoading = false;
                }
            },

            formatJson(value) {
                return JSON.stringify(value, null, 2);
            },
        },
    }).mount('#app');
</script>
@endverbatim
</body>
</html>

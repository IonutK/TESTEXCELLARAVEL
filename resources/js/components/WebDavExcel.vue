<script setup>
import { computed, onMounted, ref } from 'vue';

const fileName = ref('sample.xlsx');
const user = ref('');
const pass = ref('');

const webdavBaseUrl = computed(() => `${window.location.origin}/webdav`);
const webdavWindowsHost = computed(() => window.location.hostname);
const webdavWindowsPath = computed(() => `\\\\${webdavWindowsHost.value}@SSL\\DavWWWRoot\\webdav\\${fileName.value}`);
const webdavUrl = computed(() => `${webdavBaseUrl.value}/${encodeURIComponent(fileName.value)}`);
const webdavUrlWithAuth = computed(() => {
    if (!user.value || !pass.value) return webdavUrl.value;
    const safeUser = encodeURIComponent(user.value);
    const safePass = encodeURIComponent(pass.value);
    return `${window.location.protocol}//${safeUser}:${safePass}@${window.location.host}/webdav/${encodeURIComponent(fileName.value)}`;
});
const excelProtocolUrl = computed(() => `ms-excel:ofe|u|${webdavUrl.value}`);
const excelProtocolUrlWithAuth = computed(() => `ms-excel:ofe|u|${webdavUrlWithAuth.value}`);
const excelProtocolUrlWindows = computed(() => `ms-excel:ofe|u|${webdavWindowsPath.value}`);

const files = ref([]);
const loadingFiles = ref(false);
const uploadFile = ref(null);
const statusMessage = ref('');

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const fetchFiles = async () => {
    loadingFiles.value = true;
    statusMessage.value = '';
    try {
        const response = await fetch('/webdav/files');
        const data = await response.json();
        files.value = data.files || [];
        if (files.value.length && !files.value.includes(fileName.value)) {
            fileName.value = files.value[0];
        }
    } catch (error) {
        statusMessage.value = 'No se pudieron cargar los archivos.';
    } finally {
        loadingFiles.value = false;
    }
};

const onFileSelected = (event) => {
    const [file] = event.target.files;
    uploadFile.value = file || null;
};

const uploadSelectedFile = async () => {
    if (!uploadFile.value) {
        statusMessage.value = 'Selecciona un archivo primero.';
        return;
    }

    const form = new FormData();
    form.append('file', uploadFile.value);
    statusMessage.value = 'Subiendo archivo...';

    try {
        const response = await fetch('/webdav/upload', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
            body: form,
        });

        if (!response.ok) {
            throw new Error('Upload failed');
        }

        const data = await response.json();
        statusMessage.value = `Archivo subido: ${data.file}`;
        uploadFile.value = null;
        await fetchFiles();
        if (data.file) {
            fileName.value = data.file;
        }
    } catch (error) {
        statusMessage.value = 'Error subiendo el archivo.';
    }
};

const authHeader = computed(() => {
    if (!user.value || !pass.value) return '';
    const token = btoa(`${user.value}:${pass.value}`);
    return `Basic ${token}`;
});

onMounted(fetchFiles);
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100">
        <div class="mx-auto max-w-3xl px-6 py-10">
            <h1 class="text-3xl font-semibold">WebDAV Excel en Laravel</h1>
            <p class="mt-2 text-slate-300">
                Abre un archivo Excel desde WebDAV, edítalo en Excel y guarda. Los cambios se guardan en
                <span class="font-mono text-slate-100">storage/app/webdav</span>.
            </p>

            <div class="mt-8 space-y-6 rounded-2xl bg-slate-900/60 p-6 shadow-lg">
                <div class="space-y-4 rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Archivos disponibles</h2>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-700 px-3 py-1 text-sm text-slate-200 hover:border-slate-500"
                            @click="fetchFiles"
                        >
                            {{ loadingFiles ? 'Cargando...' : 'Actualizar' }}
                        </button>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm text-slate-300">Seleccionar archivo</label>
                            <select
                                v-model="fileName"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
                            >
                                <option v-if="!files.length" value="">Sin archivos</option>
                                <option v-for="file in files" :key="file" :value="file">
                                    {{ file }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm text-slate-300">Subir archivo Excel</label>
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-2 text-slate-100"
                                @change="onFileSelected"
                            />
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 md:flex-row md:items-center">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg bg-emerald-400 px-4 py-2 font-semibold text-slate-900 hover:bg-emerald-300"
                            @click="uploadSelectedFile"
                        >
                            Subir archivo
                        </button>
                        <span class="text-sm text-slate-300">{{ statusMessage }}</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-sm text-slate-300">Nombre del archivo</label>
                    <input
                        v-model="fileName"
                        type="text"
                        placeholder="mi-archivo.xlsx"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
                    />
                    <p class="text-xs text-slate-400">
                        El archivo debe existir en <span class="font-mono text-slate-200">storage/app/webdav</span>.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm text-slate-300">Usuario WebDAV</label>
                        <input
                            v-model="user"
                            type="text"
                            placeholder="webdav"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm text-slate-300">Contraseña WebDAV</label>
                        <input
                            v-model="pass"
                            type="password"
                            placeholder="secret"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-sm text-slate-300">URL WebDAV</label>
                    <input
                        :value="webdavUrl"
                        readonly
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-2 text-slate-100"
                    />
                </div>

                <div class="space-y-2">
                    <label class="text-sm text-slate-300">Ruta WebDAV Windows (UNC)</label>
                    <input
                        :value="webdavWindowsPath"
                        readonly
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-2 text-slate-100"
                    />
                </div>

                <div class="flex flex-col gap-3 md:flex-row">
                    <a
                        :href="excelProtocolUrl"
                        class="inline-flex items-center justify-center rounded-lg bg-sky-500 px-4 py-2 font-semibold text-slate-900 hover:bg-sky-400"
                    >
                        Abrir en Excel
                    </a>
                    <a
                        :href="excelProtocolUrlWindows"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-400 px-4 py-2 font-semibold text-slate-900 hover:bg-indigo-300"
                    >
                        Abrir en Excel (WebClient)
                    </a>
                    <a
                        v-if="user && pass"
                        :href="excelProtocolUrlWithAuth"
                        class="inline-flex items-center justify-center rounded-lg bg-emerald-400 px-4 py-2 font-semibold text-slate-900 hover:bg-emerald-300"
                    >
                        Abrir en Excel (con credenciales)
                    </a>
                    <a
                        :href="webdavUrl"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-700 px-4 py-2 text-slate-100 hover:border-slate-500"
                        target="_blank"
                        rel="noreferrer"
                    >
                        Abrir enlace WebDAV
                    </a>
                    <a
                        v-if="user && pass"
                        :href="webdavUrlWithAuth"
                        class="inline-flex items-center justify-center rounded-lg border border-emerald-400 px-4 py-2 text-emerald-200 hover:border-emerald-300"
                        target="_blank"
                        rel="noreferrer"
                    >
                        Abrir enlace WebDAV (con credenciales)
                    </a>
                </div>

                <div class="rounded-lg border border-slate-800 bg-slate-950/60 p-4 text-sm text-slate-300">
                    <p class="font-semibold text-slate-200">Notas</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>El botón de Excel usa el protocolo <span class="font-mono text-slate-200">ms-excel</span> (Windows).</li>
                        <li>Para edición, asegúrate de que el servicio de Windows <span class="font-mono text-slate-200">WebClient</span> esté iniciado.</li>
                        <li>Si sigue en solo lectura, abre usando la ruta UNC de WebDAV (botón WebClient).</li>
                        <li>Configura las credenciales WebDAV en el archivo <span class="font-mono text-slate-200">.env</span>.</li>
                        <li>
                            Si tu cliente no envía las credenciales, usa este header:
                            <span class="font-mono text-slate-200">{{ authHeader || 'Basic base64(usuario:password)' }}</span>.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

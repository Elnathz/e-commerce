<script setup>
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close', 'saved']);

const provinces = ref([]);
const cities = ref([]);
const districts = ref([]);
const isLoadingProvinces = ref(false);
const isLoadingCities = ref(false);
const isLoadingDistricts = ref(false);
const selectedDistrictId = ref('');

const form = useForm({
    label: '',
    recipient_name: '',
    phone: '',
    province: '',
    city: '',
    city_id: '',
    district: '',
    district_id: '',
    postal_code: '',
    address_detail: '',
    is_default: true,
});

const selectedProvinceId = ref('');
const selectedCityId = ref('');

// Load provinces on first open
watch(() => props.show, async (val) => {
    if (val && provinces.value.length === 0) {
        isLoadingProvinces.value = true;
        try {
            const res = await axios.get('/api/provinces');
            provinces.value = res.data;
        } catch (e) {
            console.error('Failed to load provinces', e);
        } finally {
            isLoadingProvinces.value = false;
        }
    }
});

const onProvinceChange = async (e) => {
    const provinceId = e.target.value;
    selectedProvinceId.value = provinceId;

    const selectedProv = provinces.value.find(p => p.id == provinceId);
    form.province = selectedProv ? selectedProv.name : '';
    form.city = '';
    form.city_id = '';
    form.postal_code = '';
    selectedCityId.value = '';
    cities.value = [];

    if (!provinceId) return;

    isLoadingCities.value = true;
    try {
        const res = await axios.get(`/api/cities/${provinceId}`);
        cities.value = res.data;
    } catch (e) {
        console.error('Failed to load cities', e);
    } finally {
        isLoadingCities.value = false;
    }
};

const onCityChange = async (e) => {
    const cityId = e.target.value;
    selectedCityId.value = cityId;
    
    form.district = '';
    selectedDistrictId.value = '';
    districts.value = [];

    const selectedCity = cities.value.find(c => c.id == cityId);
    if (selectedCity) {
        form.city = `${selectedCity.type} ${selectedCity.name}`;
        form.city_id = cityId;
        // Auto-fill postal code from city data
        if (selectedCity.postal_code) {
            form.postal_code = selectedCity.postal_code;
        }
        
        isLoadingDistricts.value = true;
        try {
            const res = await axios.get(`/api/districts/${cityId}`);
            districts.value = res.data;
        } catch (e) {
            console.error('Failed to load districts', e);
        } finally {
            isLoadingDistricts.value = false;
        }
    } else {
        form.city = '';
        form.city_id = '';
    }
};

const onDistrictChange = (e) => {
    const districtId = e.target.value;
    selectedDistrictId.value = districtId;
    const selectedDist = districts.value.find(d => d.id == districtId);
    form.district = selectedDist ? selectedDist.name : '';
    form.district_id = districtId;
};

// Province search/filter
const provinceSearch = ref('');
const filteredProvinces = computed(() => {
    if (!provinceSearch.value) return provinces.value;
    const q = provinceSearch.value.toLowerCase();
    return provinces.value.filter(p => p.name.toLowerCase().includes(q));
});

// City search/filter
const citySearch = ref('');
const filteredCities = computed(() => {
    if (!citySearch.value) return cities.value;
    const q = citySearch.value.toLowerCase();
    return cities.value.filter(c =>
        c.name.toLowerCase().includes(q) ||
        `${c.type} ${c.name}`.toLowerCase().includes(q)
    );
});

const submitForm = () => {
    form.post(route('addresses.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            emit('saved');
            emit('close');
        },
    });
};

const resetForm = () => {
    form.reset();
    selectedProvinceId.value = '';
    selectedCityId.value = '';
    selectedDistrictId.value = '';
    cities.value = [];
    districts.value = [];
    provinceSearch.value = '';
    citySearch.value = '';
};
</script>

<template>
    <!-- Overlay -->
    <Transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="show" class="fixed inset-0 bg-black/40 z-[80] backdrop-blur-sm" @click="emit('close')"></div>
    </Transition>

    <!-- Modal Panel -->
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-full md:translate-y-0 md:scale-95"
        enter-to-class="opacity-100 translate-y-0 md:scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 md:scale-100"
        leave-to-class="opacity-0 translate-y-full md:translate-y-0 md:scale-95"
    >
        <div v-if="show" class="fixed inset-0 z-[90] flex items-end md:items-center justify-center p-0 md:p-4">
            <div class="bg-white w-full max-w-lg rounded-t-2xl md:rounded-2xl shadow-2xl max-h-[92vh] flex flex-col">

                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                        Tambah Alamat Baru
                    </h2>
                    <button @click="emit('close')" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Scrollable Body -->
                <form @submit.prevent="submitForm" class="flex-1 overflow-y-auto px-5 py-5 space-y-4">

                    <!-- Label -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Label Alamat</label>
                        <div class="flex gap-2 flex-wrap">
                            <button v-for="lbl in ['Rumah', 'Kantor', 'Kos', 'Toko']" :key="lbl" type="button"
                                @click="form.label = lbl"
                                :class="['px-4 py-2 rounded-xl text-sm font-semibold border-2 transition-colors', form.label === lbl ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-100 text-gray-600 hover:border-blue-200']">
                                {{ lbl }}
                            </button>
                        </div>
                        <p v-if="form.errors.label" class="text-xs text-red-500 mt-1">{{ form.errors.label }}</p>
                    </div>

                    <!-- Recipient Name -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Nama Penerima</label>
                        <input v-model="form.recipient_name" type="text" placeholder="Nama lengkap penerima"
                            class="w-full h-12 px-4 rounded-xl bg-gray-50 border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 text-sm" />
                        <p v-if="form.errors.recipient_name" class="text-xs text-red-500 mt-1">{{ form.errors.recipient_name }}</p>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">No. Telepon</label>
                        <input v-model="form.phone" type="tel" placeholder="08xxxxxxxxxx"
                            class="w-full h-12 px-4 rounded-xl bg-gray-50 border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 text-sm" />
                        <p v-if="form.errors.phone" class="text-xs text-red-500 mt-1">{{ form.errors.phone }}</p>
                    </div>

                    <!-- Province (full width for mobile) -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Provinsi</label>
                        <select @change="onProvinceChange" :value="selectedProvinceId" :disabled="isLoadingProvinces"
                            class="w-full h-12 px-4 rounded-xl bg-gray-50 border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 text-sm disabled:opacity-50">
                            <option value="">{{ isLoadingProvinces ? 'Memuat provinsi...' : 'Pilih Provinsi' }}</option>
                            <option v-for="prov in provinces" :key="prov.id" :value="prov.id">{{ prov.name }}</option>
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">34 provinsi se-Indonesia</p>
                        <p v-if="form.errors.province" class="text-xs text-red-500 mt-1">{{ form.errors.province }}</p>
                    </div>

                    <!-- City (full width for mobile) -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Kota / Kabupaten</label>
                        <select @change="onCityChange" :value="selectedCityId" :disabled="isLoadingCities || !selectedProvinceId"
                            class="w-full h-12 px-4 rounded-xl bg-gray-50 border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 text-sm disabled:opacity-50">
                            <option value="">{{ isLoadingCities ? 'Memuat kota...' : (!selectedProvinceId ? 'Pilih provinsi dulu' : 'Pilih Kota / Kabupaten') }}</option>
                            <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.type }} {{ city.name }}</option>
                        </select>
                        <p v-if="cities.length > 0 && !isLoadingCities" class="text-[11px] text-gray-400 mt-1">{{ cities.length }} kota/kabupaten ditemukan</p>
                        <p v-if="form.errors.city" class="text-xs text-red-500 mt-1">{{ form.errors.city }}</p>
                    </div>

                    <!-- District & Postal Code -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Kecamatan</label>
                            <select @change="onDistrictChange" :value="selectedDistrictId" :disabled="isLoadingDistricts || !selectedCityId"
                                class="w-full h-12 px-4 rounded-xl bg-gray-50 border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 text-sm disabled:opacity-50">
                                <option value="">{{ isLoadingDistricts ? 'Memuat kecamatan...' : (!selectedCityId ? 'Pilih kota dulu' : 'Pilih Kecamatan') }}</option>
                                <option v-for="dist in districts" :key="dist.id" :value="dist.id">{{ dist.name }}</option>
                            </select>
                            <p v-if="districts.length > 0 && !isLoadingDistricts" class="text-[11px] text-gray-400 mt-1">{{ districts.length }} kecamatan ditemukan</p>
                            <p v-if="form.errors.district || form.errors.district_id" class="text-xs text-red-500 mt-1">{{ form.errors.district || form.errors.district_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Kode Pos</label>
                            <input v-model="form.postal_code" type="text" placeholder="Contoh: 50275" maxlength="5"
                                class="w-full h-12 px-4 rounded-xl bg-gray-50 border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 text-sm" />
                            <p v-if="form.postal_code" class="text-[11px] text-green-600 mt-1">✓ Terisi otomatis dari kota</p>
                        </div>
                    </div>

                    <!-- Address Detail -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Detail Alamat</label>
                        <textarea v-model="form.address_detail" rows="3" placeholder="Nama jalan, nomor rumah, RT/RW, patokan..."
                            class="w-full px-4 py-3 rounded-xl bg-gray-50 border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 text-sm resize-none"></textarea>
                        <p v-if="form.errors.address_detail" class="text-xs text-red-500 mt-1">{{ form.errors.address_detail }}</p>
                    </div>

                    <!-- Set Default -->
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" v-model="form.is_default" class="w-5 h-5 rounded-lg border-gray-300 text-blue-600 focus:ring-blue-600" />
                        <span class="text-sm font-semibold text-gray-600 group-hover:text-gray-900 transition-colors">Jadikan alamat utama</span>
                    </label>
                </form>

                <!-- Footer -->
                <div class="px-5 py-4 border-t border-gray-100 flex-shrink-0 flex gap-3 pb-8 md:pb-4">
                    <button type="button" @click="emit('close')" class="flex-1 py-3 rounded-xl text-sm font-bold border-2 border-gray-200 text-gray-600 hover:bg-gray-100 transition-colors">
                        Batal
                    </button>
                    <button @click="submitForm" :disabled="form.processing"
                        :class="['flex-1 py-3 rounded-xl text-sm font-bold text-white transition-colors', form.processing ? 'bg-blue-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700']">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Alamat' }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

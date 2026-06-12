<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    address: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const isEdit = computed(() => !!props.address);

const labelOptions = ['Rumah', 'Kantor', 'Kos', 'Toko'];

const provinces = ref([]);
const cities = ref([]);
const districts = ref([]);
const isLoadingProvinces = ref(false);
const isLoadingCities = ref(false);
const isLoadingDistricts = ref(false);

const selectedProvinceId = ref('');
const selectedCityId = ref('');
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
    is_default: false,
});

const loadProvinces = async () => {
    if (provinces.value.length > 0) return;

    isLoadingProvinces.value = true;
    try {
        const res = await axios.get(route('api.provinces'));
        provinces.value = res.data;
    } catch (e) {
        console.error('Failed to load provinces', e);
    } finally {
        isLoadingProvinces.value = false;
    }
};

const loadCities = async (provinceId) => {
    isLoadingCities.value = true;
    try {
        const res = await axios.get(route('api.cities', provinceId));
        cities.value = res.data;
    } catch (e) {
        console.error('Failed to load cities', e);
    } finally {
        isLoadingCities.value = false;
    }
};

const loadDistricts = async (cityId) => {
    isLoadingDistricts.value = true;
    try {
        const res = await axios.get(route('api.districts', cityId));
        districts.value = res.data;
    } catch (e) {
        console.error('Failed to load districts', e);
    } finally {
        isLoadingDistricts.value = false;
    }
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
    selectedProvinceId.value = '';
    selectedCityId.value = '';
    selectedDistrictId.value = '';
    cities.value = [];
    districts.value = [];
};

const populateFromAddress = async (address) => {
    form.label = address.label;
    form.recipient_name = address.recipient_name;
    form.phone = address.phone;
    form.province = address.province;
    form.city = address.city;
    form.city_id = address.city_id;
    form.district = address.district;
    form.district_id = address.district_id;
    form.postal_code = address.postal_code ?? '';
    form.address_detail = address.address_detail;
    form.is_default = !!address.is_default;
    form.clearErrors();

    const provinceId = address.city_relation?.province?.id ?? '';
    selectedProvinceId.value = provinceId ? String(provinceId) : '';
    selectedCityId.value = address.city_id ? String(address.city_id) : '';
    selectedDistrictId.value = address.district_id ? String(address.district_id) : '';

    cities.value = [];
    districts.value = [];

    if (provinceId) {
        await loadCities(provinceId);
    }
    if (address.city_id) {
        await loadDistricts(address.city_id);
    }
};

watch(() => props.show, async (visible) => {
    if (!visible) return;

    await loadProvinces();

    if (props.address) {
        await populateFromAddress(props.address);
    } else {
        resetForm();
    }
});

const onProvinceChange = async (e) => {
    const provinceId = e.target.value;
    selectedProvinceId.value = provinceId;

    const selectedProv = provinces.value.find((p) => p.id == provinceId);
    form.province = selectedProv ? selectedProv.name : '';
    form.city = '';
    form.city_id = '';
    form.district = '';
    form.district_id = '';
    form.postal_code = '';
    selectedCityId.value = '';
    selectedDistrictId.value = '';
    cities.value = [];
    districts.value = [];

    if (provinceId) {
        await loadCities(provinceId);
    }
};

const onCityChange = async (e) => {
    const cityId = e.target.value;
    selectedCityId.value = cityId;

    form.district = '';
    form.district_id = '';
    selectedDistrictId.value = '';
    districts.value = [];

    const selectedCity = cities.value.find((c) => c.id == cityId);
    if (selectedCity) {
        form.city = `${selectedCity.type} ${selectedCity.name}`;
        form.city_id = cityId;

        if (selectedCity.postal_code) {
            form.postal_code = selectedCity.postal_code;
        }

        await loadDistricts(cityId);
    } else {
        form.city = '';
        form.city_id = '';
    }
};

const onDistrictChange = (e) => {
    const districtId = e.target.value;
    selectedDistrictId.value = districtId;

    const selectedDist = districts.value.find((d) => d.id == districtId);
    form.district = selectedDist ? selectedDist.name : '';
    form.district_id = districtId;
};

const close = () => {
    emit('close');
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            emit('close');
        },
    };

    if (isEdit.value) {
        form.put(route('addresses.update', props.address.id), options);
    } else {
        form.post(route('addresses.store'), options);
    }
};

const selectClass = 'mt-1 block w-full bg-slate-50 border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-blue-600 rounded-xl text-sm font-medium text-slate-900 disabled:opacity-50';
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-900">
                    {{ isEdit ? 'Edit Alamat' : 'Tambah Alamat Baru' }}
                </h2>
                <button type="button" @click="close" class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-4 max-h-[65vh] overflow-y-auto pr-1">
                <div>
                    <InputLabel value="Label Alamat" class="font-bold text-slate-700" />
                    <div class="flex gap-2 flex-wrap mt-1.5">
                        <button v-for="lbl in labelOptions" :key="lbl" type="button"
                            @click="form.label = lbl"
                            :class="['px-3 py-1.5 rounded-lg text-sm font-semibold border-2 transition-colors', form.label === lbl ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600 hover:border-blue-300']">
                            {{ lbl }}
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.label" />
                </div>

                <div>
                    <InputLabel for="recipient_name" value="Nama Penerima" class="font-bold text-slate-700" />
                    <TextInput id="recipient_name" v-model="form.recipient_name" type="text"
                        class="mt-1 block w-full bg-slate-50 border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-blue-600 rounded-xl" />
                    <InputError class="mt-2" :message="form.errors.recipient_name" />
                </div>

                <div>
                    <InputLabel for="phone" value="No. Telepon" class="font-bold text-slate-700" />
                    <TextInput id="phone" v-model="form.phone" type="tel" placeholder="08xxxxxxxxxx"
                        class="mt-1 block w-full bg-slate-50 border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-blue-600 rounded-xl" />
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <div>
                    <InputLabel for="province" value="Provinsi" class="font-bold text-slate-700" />
                    <select id="province" @change="onProvinceChange" :value="selectedProvinceId" :disabled="isLoadingProvinces" :class="selectClass">
                        <option value="">{{ isLoadingProvinces ? 'Memuat provinsi...' : 'Pilih Provinsi' }}</option>
                        <option v-for="prov in provinces" :key="prov.id" :value="prov.id">{{ prov.name }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.province" />
                </div>

                <div>
                    <InputLabel for="city" value="Kota / Kabupaten" class="font-bold text-slate-700" />
                    <select id="city" @change="onCityChange" :value="selectedCityId" :disabled="isLoadingCities || !selectedProvinceId" :class="selectClass">
                        <option value="">{{ isLoadingCities ? 'Memuat kota...' : (!selectedProvinceId ? 'Pilih provinsi dahulu' : 'Pilih Kota / Kabupaten') }}</option>
                        <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.type }} {{ city.name }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.city" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="district" value="Kecamatan" class="font-bold text-slate-700" />
                        <select id="district" @change="onDistrictChange" :value="selectedDistrictId" :disabled="isLoadingDistricts || !selectedCityId" :class="selectClass">
                            <option value="">{{ isLoadingDistricts ? 'Memuat kecamatan...' : (!selectedCityId ? 'Pilih kota dahulu' : 'Pilih Kecamatan') }}</option>
                            <option v-for="dist in districts" :key="dist.id" :value="dist.id">{{ dist.name }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.district || form.errors.district_id" />
                    </div>
                    <div>
                        <InputLabel for="postal_code" value="Kode Pos" class="font-bold text-slate-700" />
                        <TextInput id="postal_code" v-model="form.postal_code" type="text" maxlength="5" placeholder="Contoh: 50275"
                            class="mt-1 block w-full bg-slate-50 border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-blue-600 rounded-xl" />
                        <InputError class="mt-2" :message="form.errors.postal_code" />
                    </div>
                </div>

                <div>
                    <InputLabel for="address_detail" value="Detail Alamat" class="font-bold text-slate-700" />
                    <textarea id="address_detail" v-model="form.address_detail" rows="3" placeholder="Nama jalan, nomor rumah, RT/RW, patokan..."
                        class="mt-1 block w-full bg-slate-50 border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-blue-600 rounded-xl shadow-sm text-slate-900 focus:border-blue-500"></textarea>
                    <InputError class="mt-2" :message="form.errors.address_detail" />
                </div>

                <label class="flex items-center gap-3 cursor-pointer group">
                    <input type="checkbox" v-model="form.is_default" class="w-5 h-5 rounded-lg border-slate-300 text-blue-600 focus:ring-blue-600" />
                    <span class="text-sm font-semibold text-slate-600 group-hover:text-slate-900 transition-colors">Jadikan alamat utama</span>
                </label>
            </form>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" @click="close">Batal</SecondaryButton>
                <PrimaryButton type="button" :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Alamat' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>

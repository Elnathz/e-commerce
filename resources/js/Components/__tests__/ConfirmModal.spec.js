import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ConfirmModal from '../ConfirmModal.vue';

describe('ConfirmModal', () => {
    it('renders title and message when shown', () => {
        const wrapper = mount(ConfirmModal, {
            props: { show: true, title: 'Hapus Kategori', message: 'Yakin hapus "Elektronik"?' },
        });
        expect(wrapper.text()).toContain('Hapus Kategori');
        expect(wrapper.text()).toContain('Yakin hapus "Elektronik"?');
    });

    it('emits confirm when the confirm button is clicked', async () => {
        const wrapper = mount(ConfirmModal, { props: { show: true } });
        await wrapper.find('button.bg-red-600').trigger('click');
        expect(wrapper.emitted('confirm')).toHaveLength(1);
    });

    it('emits cancel when the cancel button is clicked', async () => {
        const wrapper = mount(ConfirmModal, { props: { show: true } });
        const cancelBtn = wrapper.findAll('button').find((b) => b.text() === 'Batal');
        await cancelBtn.trigger('click');
        expect(wrapper.emitted('cancel')).toHaveLength(1);
    });
});

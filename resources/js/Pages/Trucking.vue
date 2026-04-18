<script setup>
import { Head } from "@inertiajs/vue3";
import { onMounted, reactive, ref } from "vue";
import AppLayout from "../components/layout/AppLayout.vue";
import Button from "../components/ui/Button.vue";
import Card from "../components/ui/Card.vue";
import Input from "../components/ui/Input.vue";
import Modal from "../components/ui/Modal.vue";
import Table from "../components/ui/Table.vue";
import api from "../services/api";

const trucking = ref([]);
const showModal = ref(false);

const form = reactive({
    driver_name: "",
    delivery_address: "",
    status: "pending",
});

const loadTrucking = async () => {
    const { data } = await api.get("/trucking");
    trucking.value = data.data;
};

const saveTrucking = async () => {
    await api.post("/trucking", { ...form });
    showModal.value = false;
    form.driver_name = "";
    form.delivery_address = "";
    form.status = "pending";
    await loadTrucking();
};

onMounted(loadTrucking);
</script>

<template>
    <Head title="Trucking" />

    <AppLayout title="Trucking">
        <Card title="Trucking Records">
            <div class="toolbar">
                <Button @click="showModal = true">Add Delivery</Button>
            </div>

            <Table
                :columns="['Driver', 'Delivery Address', 'Status', 'Created']"
            >
                <tr v-for="item in trucking" :key="item.id">
                    <td>{{ item.driver_name }}</td>
                    <td>{{ item.delivery_address }}</td>
                    <td>{{ item.status }}</td>
                    <td>
                        {{ new Date(item.created_at).toLocaleDateString() }}
                    </td>
                </tr>
            </Table>
        </Card>

        <Modal
            :open="showModal"
            title="Create Trucking Entry"
            @close="showModal = false"
        >
            <form class="form-grid" @submit.prevent="saveTrucking">
                <Input v-model="form.driver_name" label="Driver Name" />
                <Input
                    v-model="form.delivery_address"
                    label="Delivery Address"
                />
                <Input v-model="form.status" label="Status" />
                <div class="form-actions">
                    <Button type="submit">Save</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>

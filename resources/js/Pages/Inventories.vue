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

const inventories = ref([]);
const products = ref([]);
const showModal = ref(false);

const form = reactive({
    product_id: "",
    quantity: "",
    status: "in_stock",
});

const loadData = async () => {
    const [inventoriesRes, productsRes] = await Promise.all([
        api.get("/inventories"),
        api.get("/products"),
    ]);

    inventories.value = inventoriesRes.data.data;
    products.value = productsRes.data.data;
};

const saveInventory = async () => {
    await api.post("/inventories", {
        product_id: Number(form.product_id),
        quantity: Number(form.quantity),
        status: form.status,
    });

    showModal.value = false;
    form.product_id = "";
    form.quantity = "";
    form.status = "in_stock";
    await loadData();
};

onMounted(loadData);
</script>

<template>
    <Head title="Inventories" />

    <AppLayout title="Inventories">
        <Card title="Inventory Management">
            <div class="toolbar">
                <Button @click="showModal = true">Update Inventory</Button>
            </div>

            <Table :columns="['Product', 'Quantity', 'Status']">
                <tr v-for="item in inventories" :key="item.id">
                    <td>{{ item.product?.name || "-" }}</td>
                    <td>{{ item.quantity }}</td>
                    <td>{{ item.status }}</td>
                </tr>
            </Table>
        </Card>

        <Modal
            :open="showModal"
            title="Save Inventory"
            @close="showModal = false"
        >
            <form class="form-grid" @submit.prevent="saveInventory">
                <label class="form-field">
                    <span class="form-field__label">Product</span>
                    <select v-model="form.product_id" class="input" required>
                        <option value="">Select product</option>
                        <option
                            v-for="product in products"
                            :key="product.id"
                            :value="product.id"
                        >
                            {{ product.name }}
                        </option>
                    </select>
                </label>
                <Input v-model="form.quantity" type="number" label="Quantity" />
                <Input v-model="form.status" label="Status" />
                <div class="form-actions">
                    <Button type="submit">Save</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>

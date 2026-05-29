<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import { Filter, Search, SquarePen, Trash2 } from "lucide-vue-next";
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { toast } from "vue-sonner";
import AppLayout from "../components/layout/AppLayout.vue";
import Button from "../components/ui/Button.vue";
import Card from "../components/ui/Card.vue";
import Input from "../components/ui/Input.vue";
import Modal from "../components/ui/Modal.vue";
import Table from "../components/ui/Table.vue";
import api from "../services/api";

const page = usePage();

const canAdjustInventory = computed(() => {
    const role = page.props.auth?.user?.role ?? "staff";
    return role === "admin" || role === "manager";
});

const inventories = ref([]);
const revenueLogs = ref([]);
const products = ref([]);
const branches = ref([]);
const showModal = ref(false);
const searchQuery = ref("");
const branchFilter = ref("all");
const statusFilter = ref("all");
const showRevenueLogs = ref(false);
const editingId = ref(null);
const isSaving = ref(false);
const isPolling = ref(false);
const pollIntervalMs = 15000;
let pollTimer = null;

const form = reactive({
    branch_id: "",
    product_id: "",
    quantity: "",
});

const loadData = async () => {
    const [inventoriesRes, logsRes, productsRes, branchesRes] =
        await Promise.all([
            api.get("/inventories"),
            api.get("/inventories/revenue-logs"),
            api.get("/products"),
            api.get("/branches"),
        ]);

    inventories.value = inventoriesRes.data.data;
    revenueLogs.value = logsRes.data.data;
    products.value = productsRes.data.data;
    branches.value = branchesRes.data.data;
};

const refreshInventories = async () => {
    if (isPolling.value) {
        return;
    }

    isPolling.value = true;

    try {
        const requests = [api.get("/inventories")];

        if (showRevenueLogs.value) {
            requests.push(api.get("/inventories/revenue-logs"));
        }

        const [inventoriesRes, logsRes] = await Promise.all(requests);

        inventories.value = inventoriesRes.data.data;

        if (logsRes?.data?.data) {
            revenueLogs.value = logsRes.data.data;
        }
    } finally {
        isPolling.value = false;
    }
};

const branchOptions = computed(() =>
    [...branches.value].sort((a, b) => a.name.localeCompare(b.name)),
);

const statusOptions = computed(() => {
    const options = new Set();

    inventories.value.forEach((item) => {
        if (item.status) {
            options.add(item.status);
        }
    });

    return Array.from(options).sort((a, b) => a.localeCompare(b));
});

const formatStatusLabel = (status) =>
    String(status)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (match) => match.toUpperCase());

const formatActionLabel = (action) => {
    if (!action) {
        return "-";
    }

    return action.charAt(0).toUpperCase() + action.slice(1);
};

const formatLogDate = (value) => {
    if (!value) {
        return "-";
    }

    return new Date(value).toLocaleString();
};

const formatRevenueLogOption = (log) => {
    if (!log) {
        return "";
    }

    const date = formatLogDate(log.created_at);
    const branch = log.branch?.name || "Unassigned";
    const batch = log.batch_number || "No batch";
    const product = log.product?.name || "Product";
    const expected = formatMoney(log.expected_revenue);

    return `${date} | ${branch} | ${batch} | ${product} | Expected ₱${expected}`;
};

const filteredInventories = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return inventories.value.filter((item) => {
        const branchId = String(item.branch?.id ?? "");
        const matchesBranch =
            branchFilter.value === "all" || branchId === branchFilter.value;
        const matchesStatus =
            statusFilter.value === "all" || item.status === statusFilter.value;

        if (!matchesBranch || !matchesStatus) {
            return false;
        }

        if (!query) {
            return true;
        }

        const haystack = [
            item.branch?.name,
            item.branch?.location,
            item.product?.name,
            item.batch_number,
            item.status,
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return haystack.includes(query);
    });
});

const groupedInventories = computed(() => {
    const groups = new Map();

    filteredInventories.value.forEach((item) => {
        const branchId = item.branch?.id;
        const key = branchId ? `branch-${branchId}` : "unassigned";

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                name: item.branch?.name || "Unassigned",
                location: item.branch?.location || "",
                sortOrder: branchId ? 1 : 2,
                items: [],
            });
        }

        groups.get(key).items.push(item);
    });

    return Array.from(groups.values())
        .map((group) => {
            group.items.sort((a, b) =>
                (a.product?.name || "").localeCompare(b.product?.name || ""),
            );
            return group;
        })
        .sort((a, b) => {
            if (a.sortOrder !== b.sortOrder) {
                return a.sortOrder - b.sortOrder;
            }

            return a.name.localeCompare(b.name);
        });
});

const formatMoney = (value) => {
    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return "0.00";
    }

    return amount.toFixed(2);
};

const filteredRevenueLogs = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return revenueLogs.value.filter((log) => {
        const branchId = String(log.branch?.id ?? "");
        const matchesBranch =
            branchFilter.value === "all" || branchId === branchFilter.value;

        if (!matchesBranch) {
            return false;
        }

        if (!query) {
            return true;
        }

        const haystack = [
            log.branch?.name,
            log.branch?.location,
            log.product?.name,
            log.batch_number,
            log.action,
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return haystack.includes(query);
    });
});

const revenueLogOptions = computed(() => {
    return [...filteredRevenueLogs.value].sort((a, b) => {
        const aTime = a.created_at ? Date.parse(a.created_at) : 0;
        const bTime = b.created_at ? Date.parse(b.created_at) : 0;

        return bTime - aTime;
    });
});

const resetForm = () => {
    form.branch_id = "";
    form.product_id = "";
    form.quantity = "";
    editingId.value = null;
};

const openCreate = () => {
    resetForm();
    showModal.value = true;
};

const openEdit = (item) => {
    form.branch_id = item.branch?.id ? String(item.branch.id) : "";
    form.product_id = item.product?.id ? String(item.product.id) : "";
    form.quantity = item.quantity ?? "";
    editingId.value = item.id;
    showModal.value = true;
};

const saveInventory = async () => {
    if (isSaving.value) {
        return;
    }

    isSaving.value = true;
    const wasEditing = Boolean(editingId.value);
    const payload = {
        branch_id: Number(form.branch_id),
        product_id: Number(form.product_id),
        quantity: Number(form.quantity),
    };

    try {
        if (wasEditing) {
            await api.put(`/inventories/${editingId.value}`, payload);
        } else {
            await api.post("/inventories", payload);
        }

        showModal.value = false;
        resetForm();
        await loadData();
        toast.success(
            wasEditing ? "Inventory updated." : "Stock adjustment saved.",
        );
    } catch (err) {
        toast.error(err.response?.data?.message ?? "Failed to save inventory.");
    } finally {
        isSaving.value = false;
    }
};

const deleteInventory = async (item) => {
    if (!item?.id) {
        return;
    }

    if (!window.confirm("Delete this inventory record?")) {
        return;
    }

    await api.delete(`/inventories/${item.id}`);
    await loadData();
};

onMounted(() => {
    loadData();
    pollTimer = window.setInterval(refreshInventories, pollIntervalMs);
});

onBeforeUnmount(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
});
</script>

<template>
    <Head title="Inventories" />

    <AppLayout title="Inventories">
        <div class="products-page inventories-page">
            <Card title="Inventory Management">
                <div class="products-toolbar">
                    <div class="products-controls">
                        <label
                            class="products-control products-control--search"
                        >
                            <Search class="products-control-icon" />
                            <input
                                v-model="searchQuery"
                                class="input"
                                type="text"
                                placeholder="Search product, branch, status"
                            />
                        </label>

                        <label class="products-control">
                            <Filter class="products-control-icon" />
                            <select v-model="branchFilter" class="input">
                                <option value="all">All branches</option>
                                <option
                                    v-for="branch in branchOptions"
                                    :key="branch.id"
                                    :value="String(branch.id)"
                                >
                                    {{ branch.name }}
                                </option>
                            </select>
                        </label>

                        <label class="products-control">
                            <Filter class="products-control-icon" />
                            <select v-model="statusFilter" class="input">
                                <option value="all">All statuses</option>
                                <option
                                    v-for="status in statusOptions"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ formatStatusLabel(status) }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="products-toolbar-actions">
                        <Button
                            variant="outline"
                            class="revenue-logs-btn"
                            @click="showRevenueLogs = true"
                        >
                            Expected Revenue Logs
                            <span class="revenue-logs-btn__count">
                                {{ revenueLogOptions.length }}
                            </span>
                        </Button>
                        <Button
                            v-if="canAdjustInventory"
                            class="products-add-btn"
                            @click="openCreate"
                        >
                            Stock adjustment
                        </Button>
                    </div>
                </div>

                <Table
                    :columns="[
                        'Product',
                        'Category',
                        'Unit',
                        'Batch',
                        'Price',
                        'Quantity',
                        'Status',
                        'Actions',
                    ]"
                >
                    <tr v-if="groupedInventories.length === 0">
                        <td class="products-empty" colspan="8">
                            No inventory matches your search/filters.
                        </td>
                    </tr>
                    <template
                        v-for="group in groupedInventories"
                        :key="group.key"
                    >
                        <tr class="inventory-group-row">
                            <td class="inventory-group-cell" colspan="8">
                                <span class="inventory-group-title">
                                    {{ group.name }}
                                </span>
                                <span
                                    v-if="group.location"
                                    class="inventory-group-location"
                                >
                                    {{ group.location }}
                                </span>
                                <span class="inventory-group-count">
                                    {{ group.items.length }} items
                                </span>
                            </td>
                        </tr>
                        <tr v-for="item in group.items" :key="item.id">
                            <td>
                                <div class="inventory-product">
                                    <img
                                        v-if="item.product?.image"
                                        :src="item.product.image"
                                        :alt="
                                            item.product?.name ||
                                            'Product image'
                                        "
                                        class="inventory-product__image"
                                    />
                                    <span
                                        v-else
                                        class="inventory-product__placeholder"
                                    >
                                        -
                                    </span>
                                    <span class="inventory-product__name">
                                        {{ item.product?.name || "-" }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span
                                    v-if="item.product?.category"
                                    class="category-tag"
                                >
                                    {{ item.product.category }}
                                </span>
                                <span v-else>-</span>
                            </td>
                            <td>{{ item.product?.unit || "-" }}</td>
                            <td>{{ item.batch_number || "-" }}</td>
                            <td>{{ item.product?.price ?? "-" }}</td>
                            <td>{{ item.quantity }}</td>
                            <td>{{ item.status }}</td>
                            <td class="actions">
                                <Button
                                    variant="outline"
                                    class="products-action-btn"
                                    @click="openEdit(item)"
                                >
                                    <SquarePen class="products-btn-icon" />
                                    <span>Edit</span>
                                </Button>
                                <Button
                                    variant="danger"
                                    class="products-action-btn products-action-btn--danger"
                                    @click="deleteInventory(item)"
                                >
                                    <Trash2 class="products-btn-icon" />
                                    <span>Delete</span>
                                </Button>
                            </td>
                        </tr>
                    </template>
                </Table>
            </Card>

            <Modal
                :open="showModal"
                :title="editingId ? 'Edit Inventory' : 'Stock adjustment'"
                @close="showModal = false"
            >
                <form class="form-grid" @submit.prevent="saveInventory">
                    <label class="form-field">
                        <span class="form-field__label">Branch</span>
                        <select v-model="form.branch_id" class="input" required>
                            <option value="">Select branch</option>
                            <option
                                v-for="branch in branchOptions"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }} - {{ branch.location }}
                            </option>
                        </select>
                    </label>
                    <label class="form-field">
                        <span class="form-field__label">Product</span>
                        <select
                            v-model="form.product_id"
                            class="input"
                            required
                        >
                            <option value="">Select product</option>
                            <option
                                v-for="product in products"
                                :key="product.id"
                                :value="product.id"
                            >
                                {{ product.name }}
                                <template v-if="product.unit">
                                    ({{ product.unit }})
                                </template>
                            </option>
                        </select>
                    </label>
                    <Input
                        v-model="form.quantity"
                        type="number"
                        label="Quantity"
                    />
                    <div class="form-actions">
                        <Button type="submit" :disabled="isSaving">
                            {{ isSaving ? "Saving..." : "Save" }}
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>

        <Modal
            :open="showRevenueLogs"
            title="Expected Revenue Logs"
            @close="showRevenueLogs = false"
        >
            <div class="revenue-logs-list">
                <p
                    v-if="revenueLogOptions.length === 0"
                    class="revenue-logs-empty"
                >
                    No revenue logs found.
                </p>
                <div
                    v-for="log in revenueLogOptions"
                    :key="log.id"
                    class="revenue-log-item"
                >
                    <div class="revenue-log-item__top">
                        <span class="revenue-log-item__product">
                            {{ log.product?.name || "Product" }}
                        </span>
                        <span class="revenue-log-item__revenue">
                            ₱{{ formatMoney(log.expected_revenue) }}
                        </span>
                    </div>
                    <div class="revenue-log-item__bottom">
                        <span>{{ log.branch?.name || "Unassigned" }}</span>
                        <span>Batch: {{ log.batch_number || "-" }}</span>
                        <span>Qty: {{ log.quantity }}</span>
                        <span>{{ formatLogDate(log.created_at) }}</span>
                    </div>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

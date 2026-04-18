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

const users = ref([]);
const showModal = ref(false);
const isSaving = ref(false);

const form = reactive({
    name: "",
    email: "",
    password: "",
    role: "staff",
    profile_picture: null,
});

const onProfilePictureChange = (event) => {
    form.profile_picture = event.target.files?.[0] ?? null;
};

const loadUsers = async () => {
    const { data } = await api.get("/users");
    users.value = data.data;
};

const saveUser = async () => {
    if (isSaving.value) {
        return;
    }

    isSaving.value = true;
    const payload = new FormData();
    payload.append("name", form.name);
    payload.append("email", form.email);
    payload.append("password", form.password);
    payload.append("role", form.role);

    if (form.profile_picture) {
        payload.append("profile_picture", form.profile_picture);
    }

    try {
        await api.post("/users", payload);
        showModal.value = false;
        form.name = "";
        form.email = "";
        form.password = "";
        form.role = "staff";
        form.profile_picture = null;
        await loadUsers();
    } finally {
        isSaving.value = false;
    }
};

onMounted(loadUsers);
</script>

<template>
    <Head title="Users" />

    <AppLayout title="Users">
        <Card title="User Management">
            <div class="toolbar">
                <Button @click="showModal = true">Add User</Button>
            </div>

            <Table :columns="['Profile', 'Name', 'Email', 'Role', 'Status']">
                <tr v-for="user in users" :key="user.id">
                    <td>
                        <img
                            v-if="user.profile_picture"
                            :src="user.profile_picture"
                            :alt="user.name"
                            class="table-avatar"
                        />
                        <span v-else>-</span>
                    </td>
                    <td>{{ user.name }}</td>
                    <td>{{ user.email }}</td>
                    <td>{{ user.role }}</td>
                    <td>{{ user.is_active ? "Active" : "Inactive" }}</td>
                </tr>
            </Table>
        </Card>

        <Modal :open="showModal" title="Create User" @close="showModal = false">
            <form class="form-grid" @submit.prevent="saveUser">
                <Input v-model="form.name" label="Name" />
                <Input v-model="form.email" type="email" label="Email" />
                <Input
                    v-model="form.password"
                    type="password"
                    label="Password"
                />
                <Input v-model="form.role" label="Role" />
                <label class="form-field">
                    <span class="form-field__label">Profile Picture</span>
                    <input
                        class="input"
                        type="file"
                        accept="image/*"
                        @change="onProfilePictureChange"
                    />
                </label>
                <div class="form-actions">
                    <Button type="submit" :disabled="isSaving">
                        {{ isSaving ? "Saving..." : "Save" }}
                    </Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>

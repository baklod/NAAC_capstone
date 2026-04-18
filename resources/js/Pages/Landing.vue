<script setup>
import { Head, useForm } from "@inertiajs/vue3";

const form = useForm({
    email: "admin@naac.local",
    password: "Admin@123",
    remember: true,
});

const submit = () => {
    form.post("/login");
};
</script>

<template>
    <Head title="Welcome" />

    <main class="landing">
        <section class="landing__left">
            <div class="landing__badge">Agricultural Management System</div>
            <h1 class="landing__title">Naga Alta Agri Corp</h1>
            <p class="landing__tagline">
                Streamline inventory, sales, trucking, and user operations in
                one green-powered dashboard.
            </p>
        </section>

        <section class="landing__right">
            <div class="login-card">
                <h2 class="login-card__title">Admin Login</h2>
                <p class="login-card__subtitle">
                    Sign in to access the dashboard.
                </p>

                <form class="login-form" @submit.prevent="submit">
                    <label class="form-field">
                        <span class="form-field__label">Email</span>
                        <input
                            v-model="form.email"
                            class="input"
                            type="email"
                            placeholder="admin@naac.local"
                            required
                        />
                        <small v-if="form.errors.email" class="form-error">{{
                            form.errors.email
                        }}</small>
                    </label>

                    <label class="form-field">
                        <span class="form-field__label">Password</span>
                        <input
                            v-model="form.password"
                            class="input"
                            type="password"
                            placeholder="Enter password"
                            required
                        />
                        <small v-if="form.errors.password" class="form-error">{{
                            form.errors.password
                        }}</small>
                    </label>

                    <button
                        class="btn btn--primary login-btn"
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? "Signing in..." : "Login" }}
                    </button>
                </form>
            </div>
        </section>
    </main>
</template>

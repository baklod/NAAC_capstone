<script setup>
defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["close"]);
</script>

<template>
    <div v-if="open" class="modal-overlay" @click.self="emit('close')">
        <div class="modal">
            <header class="modal__header">
                <h3 class="modal__title">{{ title }}</h3>
                <button
                    class="modal__close"
                    type="button"
                    @click="emit('close')"
                >
                    x
                </button>
            </header>
            <div class="modal__body">
                <slot />
            </div>
        </div>
    </div>
</template>

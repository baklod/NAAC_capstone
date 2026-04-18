<script setup>
const props = defineProps({
    type: {
        type: String,
        default: "button",
    },
    variant: {
        type: String,
        default: "primary",
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button
        class="btn"
        :class="[`btn--${props.variant}`]"
        :type="props.type"
        :disabled="props.disabled"
    >
        <slot />
    </button>
</template>

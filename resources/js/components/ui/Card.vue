<script setup>
defineProps({
    title: {
        type: String,
        default: "",
    },
});
</script>

<template>
    <section class="ui-card">
        <header v-if="title" class="ui-card__header">
            <h3 class="ui-card__title">{{ title }}</h3>
        </header>
        <div class="ui-card__body">
            <slot />
        </div>
    </section>
</template>

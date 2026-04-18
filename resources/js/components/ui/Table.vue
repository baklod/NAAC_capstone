<script setup>
defineProps({
    columns: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th v-for="column in columns" :key="column">
                        {{ column }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <slot />
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import VueApexCharts from "vue3-apexcharts";
import AppLayout from "../components/layout/AppLayout.vue";
import Card from "../components/ui/Card.vue";
import Table from "../components/ui/Table.vue";
import api from "../services/api";

const POLL_INTERVAL_MS = 10000;
const SACK_TO_KILO = 50;
const METHOD_BAG = "bag";
const METHOD_SACK = "sack";
const METHOD_KILO = "kilo";
const insightLanguageOptions = [
    { value: "english", label: "English" },
    { value: "tagalog", label: "Tagalog (Casual)" },
    { value: "naga_bicol", label: "Naga Bicol (Casual, Central Bikol)" },
];

const sales = ref([]);
const searchQuery = ref("");
const dateFrom = ref("");
const dateTo = ref("");
let pollTimerId = null;
let isRefreshing = false;
const aiInsights = ref([]);
const aiInsightSource = ref("fallback");
const aiInsightStatus = ref("");
const isLoadingAiInsight = ref(false);
let aiInsightRequestId = 0;
const aiSuggestionItems = ref([]);
const aiSuggestionMode = ref("");
const selectedInsightLanguage = ref("english");
const hasGeneratedAiInsight = ref(false);

const getCreatedAtDate = (sale) => {
    const date = new Date(sale.created_at);

    return Number.isNaN(date.getTime()) ? null : date;
};

const normalizeUnitType = (unitValue) => {
    const unit = String(unitValue || "")
        .trim()
        .toLowerCase();

    if (!unit) {
        return "other";
    }

    if (unit.includes("bag")) {
        return METHOD_BAG;
    }

    if (unit.includes("sack")) {
        return METHOD_SACK;
    }

    if (unit.includes("kilo") || unit.includes("kg")) {
        return METHOD_KILO;
    }

    return "other";
};

const getSaleMethod = (sale) => {
    const saleMethod = normalizeUnitType(sale.unit_type);
    const productMethod = normalizeUnitType(sale.product?.unit);

    // Keep legacy rows accurate when old data stored "sack" for bag products.
    if (saleMethod === METHOD_SACK && productMethod === METHOD_BAG) {
        return METHOD_BAG;
    }

    if (saleMethod !== "other") {
        return saleMethod;
    }

    return productMethod;
};

const getMethodLabel = (method) => {
    if (method === METHOD_BAG) {
        return `Bag (${SACK_TO_KILO}kg)`;
    }

    if (method === METHOD_SACK) {
        return `Sack (${SACK_TO_KILO}kg)`;
    }

    if (method === METHOD_KILO) {
        return "Per Kilo";
    }

    return "Other";
};

const packedTypeLabel = computed(() => {
    const hasBag = comparisonRows.value.some(
        (row) => row.method === METHOD_BAG,
    );
    const hasSack = comparisonRows.value.some(
        (row) => row.method === METHOD_SACK,
    );

    if (hasBag && hasSack) {
        return "Bag/Sack";
    }

    if (hasBag) {
        return "Bag";
    }

    if (hasSack) {
        return "Sack";
    }

    return "Pack Unit";
});

const packedTypeInsightLabel = computed(() => {
    const label = packedTypeLabel.value;

    if (label === "Bag/Sack") {
        return "bag or sack";
    }

    return label.toLowerCase();
});

const formatDateKey = (dateValue) => {
    const date = new Date(dateValue);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
};

const formatShortDate = (dateKey) => {
    const date = new Date(`${dateKey}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return dateKey;
    }

    return date.toLocaleDateString(undefined, {
        month: "short",
        day: "numeric",
    });
};

const dateFilteredSales = computed(() => {
    const fromDate = dateFrom.value
        ? new Date(`${dateFrom.value}T00:00:00`)
        : null;
    const toDate = dateTo.value
        ? new Date(`${dateTo.value}T23:59:59.999`)
        : null;

    return sales.value.filter((sale) => {
        const createdAt = getCreatedAtDate(sale);

        if (fromDate && (!createdAt || createdAt < fromDate)) {
            return false;
        }

        if (toDate && (!createdAt || createdAt > toDate)) {
            return false;
        }

        return true;
    });
});

const searchedSales = computed(() => {
    const keyword = searchQuery.value.trim().toLowerCase();

    if (!keyword) {
        return dateFilteredSales.value;
    }

    return dateFilteredSales.value.filter((sale) => {
        const methodLabel = getMethodLabel(getSaleMethod(sale));
        const searchable = [
            sale.sale_number,
            sale.product?.name,
            methodLabel,
            String(sale.quantity ?? ""),
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return searchable.includes(keyword);
    });
});

const comparisonRows = computed(() => {
    const grouped = new Map();

    searchedSales.value.forEach((sale) => {
        const method = getSaleMethod(sale);

        if (
            method !== METHOD_SACK &&
            method !== METHOD_BAG &&
            method !== METHOD_KILO
        ) {
            return;
        }

        const productId = sale.product_id ?? sale.product?.id ?? "unknown";
        const productName = sale.product?.name || "Unknown Product";
        const key = `${productId}-${method}`;
        const quantity = Number(sale.quantity || 0);
        const totalPrice = Number(sale.total_price || 0);

        if (!grouped.has(key)) {
            grouped.set(key, {
                id: key,
                productName,
                method,
                methodLabel: getMethodLabel(method),
                qtySold: 0,
                totalSales: 0,
                totalWeightKg: 0,
            });
        }

        const row = grouped.get(key);
        row.qtySold += quantity;
        row.totalSales += totalPrice;
        row.totalWeightKg +=
            method === METHOD_SACK || method === METHOD_BAG
                ? quantity * SACK_TO_KILO
                : quantity;
    });

    return Array.from(grouped.values())
        .map((row) => ({
            ...row,
            averagePrice: row.qtySold > 0 ? row.totalSales / row.qtySold : 0,
        }))
        .sort((a, b) => b.totalSales - a.totalSales);
});

const methodTotals = computed(() => {
    const totals = {
        [METHOD_SACK]: {
            revenue: 0,
            quantity: 0,
            weightKg: 0,
        },
        [METHOD_BAG]: {
            revenue: 0,
            quantity: 0,
            weightKg: 0,
        },
        [METHOD_KILO]: {
            revenue: 0,
            quantity: 0,
            weightKg: 0,
        },
    };

    comparisonRows.value.forEach((row) => {
        totals[row.method].revenue += row.totalSales;
        totals[row.method].quantity += row.qtySold;
        totals[row.method].weightKg += row.totalWeightKg;
    });

    return totals;
});

const totalSalesPack = computed(
    () =>
        methodTotals.value[METHOD_SACK].revenue +
        methodTotals.value[METHOD_BAG].revenue,
);
const totalSalesKilo = computed(() => methodTotals.value[METHOD_KILO].revenue);

const profitDifference = computed(
    () => totalSalesPack.value - totalSalesKilo.value,
);

const bestSellingMethod = computed(() => {
    if (totalSalesPack.value === 0 && totalSalesKilo.value === 0) {
        return "No sales yet";
    }

    if (totalSalesPack.value > totalSalesKilo.value) {
        return packedTypeLabel.value;
    }

    if (totalSalesKilo.value > totalSalesPack.value) {
        return "Per Kilo";
    }

    return "Tie";
});

const totalWeightSold = computed(
    () =>
        methodTotals.value[METHOD_SACK].weightKg +
        methodTotals.value[METHOD_BAG].weightKg +
        methodTotals.value[METHOD_KILO].weightKg,
);

const barChartSeries = computed(() => [
    {
        name: "Revenue",
        data: [totalSalesPack.value, totalSalesKilo.value],
    },
]);

const barChartOptions = computed(() => ({
    chart: {
        type: "bar",
        toolbar: { show: false },
        animations: {
            enabled: true,
            speed: 220,
        },
    },
    colors: ["#16a34a"],
    plotOptions: {
        bar: {
            borderRadius: 6,
            columnWidth: "46%",
        },
    },
    dataLabels: { enabled: false },
    xaxis: {
        categories: [packedTypeLabel.value, "Per Kilo"],
        labels: {
            style: {
                colors: "#64748b",
            },
        },
    },
    yaxis: {
        labels: {
            formatter: (value) => Number(value || 0).toFixed(0),
            style: {
                colors: "#94a3b8",
            },
        },
    },
    grid: {
        borderColor: "#e2e8f0",
        strokeDashArray: 4,
    },
    tooltip: {
        theme: "light",
        y: {
            formatter: (value) => `₱ ${formatCurrency(value)}`,
        },
    },
}));

const trendData = computed(() => {
    const byDate = new Map();

    searchedSales.value.forEach((sale) => {
        const method = getSaleMethod(sale);

        if (
            method !== METHOD_SACK &&
            method !== METHOD_BAG &&
            method !== METHOD_KILO
        ) {
            return;
        }

        const dateKey = formatDateKey(sale.created_at);

        if (!dateKey) {
            return;
        }

        if (!byDate.has(dateKey)) {
            byDate.set(dateKey, {
                [METHOD_SACK]: 0,
                [METHOD_BAG]: 0,
                [METHOD_KILO]: 0,
            });
        }

        const entry = byDate.get(dateKey);
        entry[method] += Number(sale.total_price || 0);
    });

    const sortedEntries = Array.from(byDate.entries()).sort((a, b) =>
        a[0].localeCompare(b[0]),
    );

    return {
        labels: sortedEntries.map(([dateKey]) => formatShortDate(dateKey)),
        pack: sortedEntries.map(
            ([, values]) => values[METHOD_SACK] + values[METHOD_BAG],
        ),
        kilo: sortedEntries.map(([, values]) => values[METHOD_KILO]),
    };
});

const trendSeries = computed(() => [
    {
        name: `${packedTypeLabel.value} Revenue`,
        data: trendData.value.pack,
    },
    {
        name: "Per Kilo Revenue",
        data: trendData.value.kilo,
    },
]);

const trendOptions = computed(() => ({
    chart: {
        type: "line",
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: {
            enabled: true,
            speed: 240,
        },
    },
    colors: ["#16a34a", "#65a30d"],
    stroke: {
        curve: "smooth",
        width: 2,
    },
    markers: {
        size: 4,
    },
    xaxis: {
        categories: trendData.value.labels,
        labels: {
            style: {
                colors: "#64748b",
            },
        },
    },
    yaxis: {
        labels: {
            formatter: (value) => Number(value || 0).toFixed(0),
            style: {
                colors: "#94a3b8",
            },
        },
    },
    grid: {
        borderColor: "#e2e8f0",
        strokeDashArray: 4,
    },
    legend: {
        position: "bottom",
        labels: {
            colors: "#334155",
        },
    },
    tooltip: {
        theme: "light",
        y: {
            formatter: (value) => `₱ ${formatCurrency(value)}`,
        },
    },
}));

const fallbackInsights = computed(() => {
    const insights = [];
    const difference = profitDifference.value;
    const packWeight =
        methodTotals.value[METHOD_SACK].weightKg +
        methodTotals.value[METHOD_BAG].weightKg;
    const kiloWeight = methodTotals.value[METHOD_KILO].weightKg;

    if (totalSalesPack.value === 0 && totalSalesKilo.value === 0) {
        insights.push(
            `No ${packedTypeInsightLabel.value} or per kilo sales found for the selected filters.`,
        );
        insights.push(
            "Start recording unit type in sales to unlock deeper method analysis.",
        );

        return insights;
    }

    if (difference > 0) {
        insights.push(
            `Selling per ${packedTypeInsightLabel.value} gives higher revenue by ₱ ${formatCurrency(Math.abs(difference))}.`,
        );
    } else if (difference < 0) {
        insights.push(
            `Selling per kilo gives higher revenue by ₱ ${formatCurrency(Math.abs(difference))}.`,
        );
    } else {
        insights.push(
            `${packedTypeLabel.value} and per kilo currently generate equal revenue.`,
        );
    }

    if (kiloWeight > packWeight) {
        insights.push(
            `Per kilo sells more volume by ${formatCount(kiloWeight - packWeight)} kg.`,
        );
    } else if (packWeight > kiloWeight) {
        insights.push(
            `${packedTypeLabel.value} method moves more volume by ${formatCount(packWeight - kiloWeight)} kg.`,
        );
    } else if (packWeight > 0 || kiloWeight > 0) {
        insights.push("Both methods currently move the same volume.");
    }

    const packRevenuePerKg =
        packWeight > 0 ? totalSalesPack.value / packWeight : 0;
    const kiloRevenuePerKg =
        kiloWeight > 0 ? totalSalesKilo.value / kiloWeight : 0;

    if (packRevenuePerKg > 0 && kiloRevenuePerKg > 0) {
        if (packRevenuePerKg > kiloRevenuePerKg) {
            insights.push(
                `Per ${packedTypeInsightLabel.value} yields higher estimated revenue per kilogram.`,
            );
        } else if (kiloRevenuePerKg > packRevenuePerKg) {
            insights.push(
                "Per kilo yields higher estimated revenue per kilogram.",
            );
        } else {
            insights.push(
                "Both methods have similar estimated revenue per kilogram.",
            );
        }
    }

    insights.push(
        "Add product cost fields to compute true profit margin by method.",
    );

    return insights;
});

const displayedInsights = computed(() => {
    if (!hasGeneratedAiInsight.value) {
        return [];
    }

    return aiInsights.value.length ? aiInsights.value : fallbackInsights.value;
});

const insightParagraphText = computed(() => {
    if (!displayedInsights.value.length) {
        return "";
    }

    return displayedInsights.value
        .map((insight) => String(insight || "").trim())
        .filter(Boolean)
        .map((insight) => (/[.!?]$/u.test(insight) ? insight : `${insight}.`))
        .join(" ");
});

const selectedInsightLanguageLabel = computed(
    () =>
        insightLanguageOptions.find(
            (option) => option.value === selectedInsightLanguage.value,
        )?.label ?? "English",
);

const insightStatusVariant = computed(() => {
    if (!hasGeneratedAiInsight.value) {
        return "idle";
    }

    if (isLoadingAiInsight.value) {
        return "loading";
    }

    if (aiInsightStatus.value) {
        return "warning";
    }

    if (aiInsightSource.value === "groq") {
        return "ai";
    }

    return "fallback";
});

const insightStatusLabel = computed(() => {
    if (insightStatusVariant.value === "loading") {
        return "Generating";
    }

    if (insightStatusVariant.value === "warning") {
        return "Fallback Active";
    }

    if (insightStatusVariant.value === "ai") {
        return "AI Generated";
    }

    if (insightStatusVariant.value === "fallback") {
        return "Computed Insight";
    }

    return "Ready";
});

const insightStatusDetail = computed(() => {
    if (!hasGeneratedAiInsight.value) {
        return "Pick a language/dialect, then click Generate Insight.";
    }

    if (isLoadingAiInsight.value) {
        return `Generating AI insight in ${selectedInsightLanguageLabel.value}...`;
    }

    if (aiInsightStatus.value) {
        return aiInsightStatus.value;
    }

    if (aiInsightSource.value === "groq") {
        return `AI-enhanced insights powered by Groq in ${selectedInsightLanguageLabel.value}.`;
    }

    return `Showing computed insights for ${selectedInsightLanguageLabel.value}.`;
});

const trendRevenueSeries = computed(() =>
    trendData.value.pack.map(
        (packRevenue, index) =>
            Number(packRevenue || 0) + Number(trendData.value.kilo[index] || 0),
    ),
);

const salesTrendDirection = computed(() => {
    const values = trendRevenueSeries.value.filter((value) =>
        Number.isFinite(value),
    );

    if (values.length < 2) {
        return "stable";
    }

    if (values.length < 4) {
        const delta = values[values.length - 1] - values[0];

        if (delta > 0) {
            return "up";
        }

        if (delta < 0) {
            return "down";
        }

        return "stable";
    }

    const splitIndex = Math.floor(values.length / 2);
    const firstWindow = values.slice(0, splitIndex);
    const secondWindow = values.slice(splitIndex);
    const firstAverage =
        firstWindow.reduce((sum, value) => sum + value, 0) / firstWindow.length;
    const secondAverage =
        secondWindow.reduce((sum, value) => sum + value, 0) /
        secondWindow.length;

    if (secondAverage > firstAverage * 1.03) {
        return "up";
    }

    if (secondAverage < firstAverage * 0.97) {
        return "down";
    }

    return "stable";
});

const suggestionMode = computed(() => {
    if (
        aiSuggestionMode.value === "improve" ||
        aiSuggestionMode.value === "maintain"
    ) {
        return aiSuggestionMode.value;
    }

    if (!insightParagraphText.value) {
        return "idle";
    }

    const totalRevenue = totalSalesPack.value + totalSalesKilo.value;

    if (totalRevenue <= 0) {
        return "improve";
    }

    if (salesTrendDirection.value === "down") {
        return "improve";
    }

    if (profitDifference.value < 0) {
        return "improve";
    }

    return "maintain";
});

const suggestionContent = computed(() => {
    const mode = suggestionMode.value;
    const language = selectedInsightLanguage.value;
    const method = bestSellingMethod.value;

    if (mode === "idle") {
        return {
            title: "",
            intro: "",
            items: [],
        };
    }

    if (language === "tagalog") {
        if (mode === "improve") {
            return {
                title: "Suggestions para tumaas ang sales",
                intro: "Medyo kailangan pa iangat ang trend. Subukan ito this week:",
                items: [
                    `Tutukan yung products na malakas sa ${method}, tapos lagyan ng simpleng promo yung mas mahina.`,
                    "Mag test ng maliit na price adjustment (hal. ₱10-₱20) at i-check ang daily effect sa benta.",
                    "I-bundle ang slow-moving items sa top products para tumaas ang total kada transaksyon.",
                    "Mag weekly review sa pinaka-mahinang rows at unahin i-restock yung mabilis maubos.",
                ],
            };
        }

        return {
            title: "Suggestions para ma-maintain ang magandang sales",
            intro: "Maganda ang takbo ng benta. Ito ang puwedeng ituloy:",
            items: [
                "Panatilihin ang stock buffer sa top-selling products para iwas out-of-stock sa peak days.",
                "I-keep muna ang price points na gumagana, at maliit lang na tests kapag mabagal ang araw.",
                `I-monitor weekly ang ${method} kontra sa ibang method para maagapan agad kapag may dip.`,
                "Magbigay ng light rewards sa repeat buyers para steady ang volume at balik-bili.",
            ],
        };
    }

    if (language === "naga_bicol") {
        if (mode === "improve") {
            return {
                title: "Mga paagi para mapauswag pa an benta",
                intro: "Medyo mahina pa an trend. Tistinga ini na practical na paagi:",
                items: [
                    `Tutokan ta an products na maray an benta sa ${method}, tapos mag simple promo sa mas mahina.`,
                    "Mag test nin gamay na pag-adjust sa presyo (hal. ₱10-₱20) tapos bantayan an effect kada adlaw.",
                    "Ipares an slow-moving items sa top products tanganing tumaas an total kada transaksyon.",
                    "Reviewhon kada semana an pinakahinay na products asin i-prioritize an mabilis umikot na stock.",
                ],
            };
        }

        return {
            title: "Mga paagi para mapanatili an maray na benta",
            intro: "Maray an dagan nin sales ngunyan. Ini an pwedeng padagoson:",
            items: [
                "Panatilihon an stock buffer sa top-selling products para dai maubusan sa peak na adlaw.",
                "I-keep an presyo na nagana, asin mag test lang nin gamay na change sa mga mahihinang adlaw.",
                `Padagoson an weekly check sa ${method} versus ibang method para maagapan an dip.`,
                "Mag offer nin simple reward sa repeat customers para tuloy-tuloy an volume nin benta.",
            ],
        };
    }

    if (mode === "improve") {
        return {
            title: "Suggestions to Improve Sales",
            intro: "Sales trend needs a lift. Try these actions this week:",
            items: [
                `Push products with stronger demand in ${method}, then run light promos on slower methods.`,
                "Test small price changes (about ₱10-₱20) and monitor daily conversion before scaling.",
                "Bundle slow-moving products with top sellers to increase average transaction value.",
                "Review low-performing rows weekly and prioritize restock for high-rotation items.",
            ],
        };
    }

    return {
        title: "Suggestions to Maintain Strong Sales",
        intro: "Sales are looking healthy. Keep momentum with these habits:",
        items: [
            "Maintain stock buffer on top sellers to avoid missed sales during peak hours.",
            "Keep current winning price points and run only controlled micro-tests on slow days.",
            `Track ${method} versus alternative methods weekly so you can react quickly to early dips.`,
            "Use light loyalty offers for repeat buyers to sustain volume and customer retention.",
        ],
    };
});

const suggestionTitle = computed(() => suggestionContent.value.title);
const suggestionIntro = computed(() => suggestionContent.value.intro);
const suggestionItems = computed(() =>
    aiSuggestionItems.value.length
        ? aiSuggestionItems.value
        : suggestionContent.value.items,
);

const aiInsightSummary = computed(() => ({
    packed_label: packedTypeLabel.value,
    best_selling_method: bestSellingMethod.value,
    total_sales_pack: Number(totalSalesPack.value || 0),
    total_sales_kilo: Number(totalSalesKilo.value || 0),
    profit_difference: Number(profitDifference.value || 0),
    total_weight_sold_kg: Number(totalWeightSold.value || 0),
    rows: comparisonRows.value.slice(0, 12).map((row) => ({
        product: row.productName,
        type: row.methodLabel,
        qty_sold: Number(row.qtySold || 0),
        average_price: Number(row.averagePrice || 0),
        total: Number(row.totalSales || 0),
    })),
}));

const topComparisonRow = computed(() => comparisonRows.value[0] ?? null);

const metricCards = computed(() => [
    {
        key: "pack",
        label: `Total Sales (${packedTypeLabel.value})`,
        value: `₱ ${formatCurrency(totalSalesPack.value)}`,
    },
    {
        key: "kilo",
        label: "Total Sales (Per Kilo)",
        value: `₱ ${formatCurrency(totalSalesKilo.value)}`,
    },
    {
        key: "difference",
        label: "Profit Difference",
        value: `${profitDifference.value >= 0 ? "+" : "-"}₱ ${formatCurrency(
            Math.abs(profitDifference.value),
        )}`,
        tone:
            profitDifference.value > 0
                ? "positive"
                : profitDifference.value < 0
                  ? "negative"
                  : "neutral",
    },
    {
        key: "method",
        label: "Best Selling Method",
        value: bestSellingMethod.value,
        valueType: "text",
    },
]);

const formatCurrency = (value) =>
    Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const formatCount = (value) => Number(value || 0).toLocaleString();

const formatQuantityByMethod = (row) => {
    if (row.method === METHOD_BAG) {
        return `${formatCount(row.qtySold)} bag(s)`;
    }

    if (row.method === METHOD_SACK) {
        return `${formatCount(row.qtySold)} sack(s)`;
    }

    return `${formatCount(row.qtySold)} kg`;
};

const refreshAiInsights = async () => {
    const requestId = ++aiInsightRequestId;
    hasGeneratedAiInsight.value = true;
    isLoadingAiInsight.value = true;
    aiInsightStatus.value = "";
    aiSuggestionItems.value = [];
    aiSuggestionMode.value = "";

    try {
        const { data } = await api.post("/profit-analysis/insights", {
            summary: aiInsightSummary.value,
            fallback_insights: fallbackInsights.value,
            language: selectedInsightLanguage.value,
        });

        if (requestId !== aiInsightRequestId) {
            return;
        }

        const insights = data?.data?.insights;
        const suggestions = data?.data?.suggestions;
        const suggestionModeFromApi = data?.data?.suggestion_mode;
        aiInsightSource.value = data?.data?.source || "fallback";
        aiInsightStatus.value = data?.data?.message || "";
        aiInsights.value =
            Array.isArray(insights) && insights.length
                ? insights
                : fallbackInsights.value;
        aiSuggestionMode.value =
            suggestionModeFromApi === "improve" ||
            suggestionModeFromApi === "maintain"
                ? suggestionModeFromApi
                : "";
        aiSuggestionItems.value = Array.isArray(suggestions)
            ? suggestions
                  .map((item) => String(item || "").trim())
                  .filter(Boolean)
                  .slice(0, 4)
            : [];
    } catch (error) {
        if (requestId !== aiInsightRequestId) {
            return;
        }

        aiInsightSource.value = "fallback";
        aiInsightStatus.value =
            "AI insight is temporarily unavailable. Showing computed insights.";
        aiInsights.value = fallbackInsights.value;
        aiSuggestionItems.value = [];
        aiSuggestionMode.value = "";
    } finally {
        if (requestId === aiInsightRequestId) {
            isLoadingAiInsight.value = false;
        }
    }
};

const resetFilters = () => {
    searchQuery.value = "";
    dateFrom.value = "";
    dateTo.value = "";
};

const loadSales = async () => {
    if (isRefreshing) {
        return;
    }

    isRefreshing = true;

    try {
        const { data } = await api.get("/sales");
        sales.value = data.data ?? [];
    } finally {
        isRefreshing = false;
    }
};

const stopPolling = () => {
    if (pollTimerId !== null) {
        window.clearInterval(pollTimerId);
        pollTimerId = null;
    }
};

const startPolling = () => {
    stopPolling();

    pollTimerId = window.setInterval(() => {
        if (document.visibilityState === "visible") {
            loadSales();
        }
    }, POLL_INTERVAL_MS);
};

const handleVisibilityChange = () => {
    if (document.visibilityState === "visible") {
        loadSales();
    }
};

watch([searchQuery, dateFrom, dateTo], () => {
    aiInsights.value = [];
    aiInsightSource.value = "fallback";
    aiInsightStatus.value = "";
    aiSuggestionItems.value = [];
    aiSuggestionMode.value = "";
    hasGeneratedAiInsight.value = false;
});

watch(selectedInsightLanguage, () => {
    aiInsights.value = [];
    aiInsightSource.value = "fallback";
    aiInsightStatus.value = "";
    aiSuggestionItems.value = [];
    aiSuggestionMode.value = "";
    hasGeneratedAiInsight.value = false;
});

onMounted(() => {
    loadSales();
    startPolling();
    document.addEventListener("visibilitychange", handleVisibilityChange);
});

onUnmounted(() => {
    stopPolling();

    document.removeEventListener("visibilitychange", handleVisibilityChange);
});
</script>

<template>
    <Head title="Product Profit Analysis" />

    <AppLayout title="Product Profit Analysis">
        <section class="sales-report-page">
            <Card class="sales-report-card" title="Product Profit Analysis">
                <div class="sales-report-filters">
                    <div class="sales-report-filters__group">
                        <label
                            class="sales-report-filter sales-report-filter--search"
                        >
                            <span class="sales-report-filter__label"
                                >Product Search</span
                            >
                            <input
                                v-model="searchQuery"
                                type="text"
                                class="input"
                                placeholder="Search product name"
                            />
                        </label>

                        <label class="sales-report-filter">
                            <span class="sales-report-filter__label">From</span>
                            <input
                                v-model="dateFrom"
                                type="date"
                                class="input"
                            />
                        </label>

                        <label class="sales-report-filter">
                            <span class="sales-report-filter__label">To</span>
                            <input v-model="dateTo" type="date" class="input" />
                        </label>
                    </div>

                    <div class="sales-report-filters__actions">
                        <p class="sales-report-meta">
                            Showing
                            {{ formatCount(comparisonRows.length) }} row(s)
                        </p>
                        <button
                            type="button"
                            class="btn btn--secondary"
                            @click="resetFilters"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <div class="profit-analysis-summary">
                    <article
                        v-for="metric in metricCards"
                        :key="metric.key"
                        class="profit-analysis-summary__item"
                        :class="{
                            'profit-analysis-summary__item--positive':
                                metric.tone === 'positive',
                            'profit-analysis-summary__item--negative':
                                metric.tone === 'negative',
                        }"
                    >
                        <p class="profit-analysis-summary__label">
                            {{ metric.label }}
                        </p>
                        <p
                            class="profit-analysis-summary__value"
                            :class="{
                                'profit-analysis-summary__value--text':
                                    metric.valueType === 'text',
                            }"
                        >
                            {{ metric.value }}
                        </p>
                    </article>
                </div>

                <p class="profit-analysis-note">
                    Conversion logic: 1 {{ packedTypeInsightLabel }} =
                    {{ SACK_TO_KILO }} kg (for packed-unit sales). Total weight
                    sold: {{ formatCount(totalWeightSold) }} kg.
                </p>

                <div class="profit-analysis-insight">
                    <div class="profit-analysis-insight__head">
                        <div class="profit-analysis-insight__title-wrap">
                            <h4 class="profit-analysis-insight__title">
                                Smart Insight
                            </h4>
                            <p class="profit-analysis-insight__subtitle">
                                Clear, action-focused recommendations for
                                pricing and selling decisions.
                            </p>
                        </div>

                        <div class="profit-analysis-insight__controls">
                            <label class="profit-analysis-insight__control">
                                <span class="sales-report-filter__label">
                                    Language / Dialect
                                </span>
                                <select
                                    v-model="selectedInsightLanguage"
                                    class="input"
                                >
                                    <option
                                        v-for="option in insightLanguageOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </label>

                            <button
                                type="button"
                                class="btn profit-analysis-insight__button"
                                :disabled="isLoadingAiInsight"
                                @click="refreshAiInsights"
                            >
                                {{
                                    isLoadingAiInsight
                                        ? "Generating..."
                                        : "Generate Insight"
                                }}
                            </button>
                        </div>
                    </div>

                    <div class="profit-analysis-insight__status-row">
                        <span
                            class="profit-analysis-insight__badge profit-analysis-insight__badge--language"
                        >
                            {{ selectedInsightLanguageLabel }}
                        </span>
                        <span
                            class="profit-analysis-insight__badge"
                            :class="{
                                'profit-analysis-insight__badge--idle':
                                    insightStatusVariant === 'idle',
                                'profit-analysis-insight__badge--loading':
                                    insightStatusVariant === 'loading',
                                'profit-analysis-insight__badge--warning':
                                    insightStatusVariant === 'warning',
                                'profit-analysis-insight__badge--ai':
                                    insightStatusVariant === 'ai',
                                'profit-analysis-insight__badge--fallback':
                                    insightStatusVariant === 'fallback',
                            }"
                        >
                            {{ insightStatusLabel }}
                        </span>
                    </div>

                    <p
                        class="profit-analysis-insight__meta"
                        :class="{
                            'profit-analysis-insight__meta--warning':
                                insightStatusVariant === 'warning',
                        }"
                    >
                        {{ insightStatusDetail }}
                    </p>

                    <div
                        v-if="insightParagraphText"
                        class="profit-analysis-insight__paragraph-wrap"
                    >
                        <p class="profit-analysis-insight__paragraph">
                            {{ insightParagraphText }}
                        </p>
                    </div>

                    <section
                        v-if="suggestionItems.length"
                        class="profit-analysis-suggestions"
                    >
                        <h5
                            class="profit-analysis-suggestions__title"
                            :class="{
                                'profit-analysis-suggestions__title--improve':
                                    suggestionMode === 'improve',
                                'profit-analysis-suggestions__title--maintain':
                                    suggestionMode === 'maintain',
                            }"
                        >
                            {{ suggestionTitle }}
                        </h5>

                        <p class="profit-analysis-suggestions__intro">
                            {{ suggestionIntro }}
                        </p>

                        <ul class="profit-analysis-suggestions__list">
                            <li
                                v-for="(suggestion, index) in suggestionItems"
                                :key="`${index}-${suggestion}`"
                                class="profit-analysis-suggestions__item"
                            >
                                {{ suggestion }}
                            </li>
                        </ul>
                    </section>

                    <div v-else class="profit-analysis-insight__placeholder">
                        <p class="profit-analysis-insight__placeholder-text">
                            Your insight paragraph will appear here after you
                            click Generate Insight.
                        </p>
                    </div>
                </div>

                <div class="profit-analysis-charts">
                    <article class="profit-analysis-chart">
                        <header class="profit-analysis-chart__head">
                            <h4 class="profit-analysis-chart__title">
                                {{ packedTypeLabel }} vs Per Kilo Revenue
                            </h4>
                            <p class="profit-analysis-chart__subtitle">
                                Bar chart comparison
                            </p>
                        </header>

                        <div class="profit-analysis-chart__body">
                            <p
                                v-if="!comparisonRows.length"
                                class="profit-analysis-chart__state"
                            >
                                No data available for
                                {{ packedTypeInsightLabel }}/per kilo
                                comparison.
                            </p>

                            <VueApexCharts
                                v-else
                                type="bar"
                                height="280"
                                :options="barChartOptions"
                                :series="barChartSeries"
                            />
                        </div>
                    </article>

                    <article class="profit-analysis-chart">
                        <header class="profit-analysis-chart__head">
                            <h4 class="profit-analysis-chart__title">
                                Revenue Trend Over Time
                            </h4>
                            <p class="profit-analysis-chart__subtitle">
                                Line chart by method
                            </p>
                        </header>

                        <div class="profit-analysis-chart__body">
                            <p
                                v-if="!trendData.labels.length"
                                class="profit-analysis-chart__state"
                            >
                                No trend data available for selected filters.
                            </p>

                            <VueApexCharts
                                v-else
                                type="line"
                                height="280"
                                :options="trendOptions"
                                :series="trendSeries"
                            />
                        </div>
                    </article>
                </div>

                <p class="profit-analysis-section-title">Comparison Table</p>

                <Table
                    :columns="['Product', 'Type', 'Qty Sold', 'Price', 'Total']"
                >
                    <tr v-if="!comparisonRows.length">
                        <td colspan="5" class="sales-report-empty">
                            No comparison rows found for this filter.
                        </td>
                    </tr>

                    <tr v-for="row in comparisonRows" :key="row.id">
                        <td>{{ row.productName }}</td>
                        <td>
                            <span
                                class="profit-analysis-type"
                                :class="{
                                    'profit-analysis-type--bag':
                                        row.method === METHOD_BAG,
                                    'profit-analysis-type--sack':
                                        row.method === METHOD_SACK,
                                    'profit-analysis-type--kilo':
                                        row.method === METHOD_KILO,
                                }"
                            >
                                {{ row.methodLabel }}
                            </span>
                        </td>
                        <td class="profit-analysis-qty">
                            {{ formatQuantityByMethod(row) }}
                        </td>
                        <td>
                            ₱ {{ formatCurrency(row.averagePrice) }}
                            {{
                                row.method === METHOD_BAG
                                    ? "/bag"
                                    : row.method === METHOD_SACK
                                      ? "/sack"
                                      : "/kg"
                            }}
                        </td>
                        <td class="profit-analysis-profit">
                            ₱ {{ formatCurrency(row.totalSales) }}
                        </td>
                    </tr>
                </Table>

                <div class="report-total">
                    Best Row:
                    {{
                        topComparisonRow
                            ? `${topComparisonRow.productName} (${topComparisonRow.methodLabel})`
                            : "-"
                    }}
                </div>
            </Card>
        </section>
    </AppLayout>
</template>

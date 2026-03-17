<template>
  <div class="searchable-multiselect" ref="containerRef">
    <div class="searchable-multiselect-trigger" @click="openIfClosed">
      <input
        ref="searchInputRef"
        type="text"
        class="form-control"
        :placeholder="placeholder"
        v-model="searchQuery"
        @focus="isOpen = true"
        @keydown.escape="isOpen = false"
        @keydown.down.prevent="focusNext"
        @keydown.up.prevent="focusPrev"
      />
      <span class="searchable-multiselect-caret" :class="{ 'is-open': isOpen }">&#9660;</span>
    </div>
    <div v-show="isOpen" class="searchable-multiselect-dropdown">
      <div v-if="filteredOptions.length === 0" class="searchable-multiselect-empty">
        {{ searchQuery ? 'No matching feeds' : 'No feeds available' }}
      </div>
      <div
        v-else
        class="searchable-multiselect-list"
        ref="listRef"
      >
        <label
          v-for="(opt, idx) in filteredOptions"
          :key="optValue(opt)"
          class="searchable-multiselect-option"
          :class="{ 'is-focused': focusedIndex === idx }"
          :ref="(el) => setOptionRef(idx, el)"
        >
          <input
            type="checkbox"
            :value="optValue(opt)"
            :checked="isSelected(opt)"
            @change="toggleOption(opt)"
          />
          <span>{{ optLabel(opt) }}</span>
        </label>
      </div>
    </div>
    <div v-if="selectedLabels.length" class="searchable-multiselect-tags">
      <span
        v-for="label in selectedLabels"
        :key="label"
        class="searchable-multiselect-tag"
      >
        {{ label }}
      </span>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

export default {
  name: 'SearchableMultiselect',
  props: {
    options: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] },
    valueKey: { type: String, default: 'idFeedIn' },
    labelKey: { type: String, default: 'label' },
    placeholder: { type: String, default: 'Search feeds...' },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const containerRef = ref(null);
    const searchInputRef = ref(null);
    const listRef = ref(null);
    const optionRefs = ref([]);
    const isOpen = ref(false);
    const searchQuery = ref('');
    const focusedIndex = ref(-1);

    const optValue = (opt) => (typeof opt === 'object' ? opt[props.valueKey] : opt);
    const optLabel = (opt) => (typeof opt === 'object' ? opt[props.labelKey] : String(opt));

    const selectedSet = computed(() => new Set(props.modelValue.map((v) => String(v))));

    const filteredOptions = computed(() => {
      const q = searchQuery.value.trim().toLowerCase();
      if (!q) return props.options;
      return props.options.filter((opt) =>
        optLabel(opt).toLowerCase().includes(q)
      );
    });

    const selectedLabels = computed(() => {
      return props.options
        .filter((opt) => selectedSet.value.has(String(optValue(opt))))
        .map(optLabel);
    });

    const isSelected = (opt) => selectedSet.value.has(String(optValue(opt)));

    const toggleOption = (opt) => {
      const val = optValue(opt);
      const arr = [...props.modelValue];
      const idx = arr.findIndex((v) => String(v) === String(val));
      if (idx >= 0) {
        arr.splice(idx, 1);
      } else {
        arr.push(val);
      }
      emit('update:modelValue', arr);
    };

    const setOptionRef = (idx, el) => {
      if (el) {
        const arr = optionRefs.value;
        if (!Array.isArray(arr)) return;
        arr[idx] = el;
      }
    };

    const openIfClosed = () => {
      if (!isOpen.value) {
        isOpen.value = true;
        searchQuery.value = '';
        focusedIndex.value = -1;
        setTimeout(() => searchInputRef.value?.focus(), 0);
      }
    };

    const handleClickOutside = (e) => {
      if (containerRef.value && !containerRef.value.contains(e.target)) {
        isOpen.value = false;
      }
    };

    const focusNext = () => {
      if (!isOpen.value) return;
      focusedIndex.value = Math.min(focusedIndex.value + 1, filteredOptions.value.length - 1);
      optionRefs.value[focusedIndex.value]?.scrollIntoView({ block: 'nearest' });
    };

    const focusPrev = () => {
      if (!isOpen.value) return;
      focusedIndex.value = Math.max(focusedIndex.value - 1, 0);
      optionRefs.value[focusedIndex.value]?.scrollIntoView({ block: 'nearest' });
    };

    watch(isOpen, (open) => {
      if (!open) focusedIndex.value = -1;
    });

    onMounted(() => {
      document.addEventListener('click', handleClickOutside, true);
    });

    onUnmounted(() => {
      document.removeEventListener('click', handleClickOutside, true);
    });

    return {
      containerRef,
      searchInputRef,
      listRef,
      optionRefs,
      isOpen,
      searchQuery,
      focusedIndex,
      optValue,
      optLabel,
      filteredOptions,
      selectedLabels,
      isSelected,
      toggleOption,
      openIfClosed,
      setOptionRef,
    };
  },
};
</script>

<style scoped>
.searchable-multiselect {
  position: relative;
  max-width: 450px;
}
.searchable-multiselect-trigger {
  position: relative;
}
.searchable-multiselect-trigger input {
  padding-right: 28px;
}
.searchable-multiselect-caret {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 10px;
  color: #666;
  pointer-events: none;
  transition: transform 0.2s;
}
.searchable-multiselect-caret.is-open {
  transform: translateY(-50%) rotate(180deg);
}
.searchable-multiselect-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  margin-top: 4px;
  background: #fff;
  border: 1px solid #ccc;
  border-radius: 4px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 1000;
  max-height: 220px;
  overflow: hidden;
}
.searchable-multiselect-list {
  max-height: 200px;
  overflow-y: auto;
}
.searchable-multiselect-option {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  cursor: pointer;
  margin: 0;
  font-weight: normal;
  border-bottom: 1px solid #f0f0f0;
}
.searchable-multiselect-option:last-child {
  border-bottom: none;
}
.searchable-multiselect-option:hover,
.searchable-multiselect-option.is-focused {
  background: #f5f5f5;
}
.searchable-multiselect-option input {
  margin-right: 10px;
  flex-shrink: 0;
}
.searchable-multiselect-empty {
  padding: 12px;
  color: #999;
  font-size: 14px;
}
.searchable-multiselect-tags {
  margin-top: 8px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.searchable-multiselect-tag {
  display: inline-block;
  padding: 4px 10px;
  background: #e8f4fc;
  border: 1px solid #b8daff;
  border-radius: 4px;
  font-size: 13px;
  color: #004085;
}
</style>

<template>
  <div class="form-group quick-jump">
    <label>Quick Jump:</label>
    <select
      v-model="quickJumpValue"
      class="form-control"
      style="display: inline-block; width: auto; margin-right: 10px;"
      @change="applyQuickJump"
    >
      <option value="">-- Select --</option>
      <option v-for="opt in quickJumpOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>
    <label style="margin-left: 10px;">Set Dates:</label>
    <input
      :value="start"
      type="date"
      class="form-control"
      style="display: inline-block; width: auto; margin: 0 5px;"
      @input="onStartChange"
    />
    to
    <input
      :value="end"
      type="date"
      class="form-control"
      style="display: inline-block; width: auto; margin: 0 5px;"
      @input="onEndChange"
    />
    <button
      v-if="showUpdateButton"
      type="button"
      class="btn btn-primary btn-sm"
      style="margin-left: 5px; margin-top: 5px;"
      @click="$emit('change')"
    >
      Update
    </button>
  </div>
</template>

<script>
import { ref } from 'vue';

export default {
  name: 'QuickJump',
  props: {
    start: {
      type: String,
      required: true,
    },
    end: {
      type: String,
      required: true,
    },
    showUpdateButton: {
      type: Boolean,
      default: true,
    },
  },
  emits: ['update:start', 'update:end', 'change'],
  setup(props, { emit }) {
    const quickJumpValue = ref('');

    const getQuickJumpOptions = () => {
      const opts = [];
      const fmt = (d) => d.toISOString().split('T')[0];
      const add = (label, start, end) => opts.push({ label, value: `${start}|${end}` });

      const today = new Date();
      add('Today', fmt(today), fmt(today));

      const yesterday = new Date(today);
      yesterday.setDate(yesterday.getDate() - 1);
      add('Yesterday', fmt(yesterday), fmt(yesterday));

      const thisMon = new Date(today);
      thisMon.setDate(today.getDate() - today.getDay() + (today.getDay() === 0 ? -6 : 1));
      const thisSun = new Date(thisMon);
      thisSun.setDate(thisMon.getDate() + 6);
      add('This Week', fmt(thisMon), fmt(thisSun));

      const lastMon = new Date(thisMon);
      lastMon.setDate(thisMon.getDate() - 7);
      const lastSun = new Date(lastMon);
      lastSun.setDate(lastMon.getDate() + 6);
      add('Last Week', fmt(lastMon), fmt(lastSun));

      const endYear = new Date(today.getFullYear(), 11, 31);
      const startYear = new Date(today.getFullYear(), 0, 1);
      add(`${today.getFullYear()} Year`, fmt(startYear), fmt(endYear));

      for (let m = today.getMonth(); m >= 0; m--) {
        const d = new Date(today.getFullYear(), m, 1);
        const lastDay = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        add(d.toISOString().slice(0, 7), fmt(d), fmt(lastDay));
      }
      for (let y = today.getFullYear() - 1; y >= today.getFullYear() - 3; y--) {
        add(`${y} Year`, `${y}-01-01`, `${y}-12-31`);
      }
      return opts;
    };

    const quickJumpOptions = getQuickJumpOptions();

    const applyQuickJump = () => {
      const v = quickJumpValue.value;
      if (v) {
        const [startVal, endVal] = v.split('|');
        emit('update:start', startVal);
        emit('update:end', endVal);
        emit('change');
      }
    };

    const onStartChange = (e) => {
      emit('update:start', e.target.value);
      emit('change');
    };

    const onEndChange = (e) => {
      emit('update:end', e.target.value);
      emit('change');
    };

    return {
      quickJumpValue,
      quickJumpOptions,
      applyQuickJump,
      onStartChange,
      onEndChange,
    };
  },
};
</script>

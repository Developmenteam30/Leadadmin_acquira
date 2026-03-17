import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from '../composables/useAuth';
import Login from '../components/Login.vue';
import Dashboard from '../components/Dashboard.vue';
import VerticalManagement from '../components/VerticalManagement.vue';
import FieldManagement from '../components/FieldManagement.vue';
import UserManagement from '../components/UserManagement.vue';
import CompanyManagement from '../components/CompanyManagement.vue';
import IncomingFeedsManagement from '../components/IncomingFeedsManagement.vue';
import PingRequestsManagement from '../components/PingRequestsManagement.vue';
import OutgoingFeedsManagement from '../components/OutgoingFeedsManagement.vue';
import OutgoingFeedsPingManagement from '../components/OutgoingFeedsPingManagement.vue';
import RecordSearch from '../components/RecordSearch.vue';
import OutgoingRecordSearch from '../components/OutgoingRecordSearch.vue';

const routes = [
  {
    path: '/login',
    name: 'login',
    component: Login,
    meta: { requiresAuth: false },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: Dashboard,
    meta: { requiresAuth: true },
  },
  {
    path: '/verticals',
    name: 'verticals',
    component: VerticalManagement,
    meta: { requiresAuth: true },
  },
  {
    path: '/fields',
    name: 'fields',
    component: FieldManagement,
    meta: { requiresAuth: true },
  },
  {
    path: '/users',
    name: 'users',
    component: UserManagement,
    meta: { requiresAuth: true },
  },
  {
    path: '/companies',
    name: 'companies',
    component: CompanyManagement,
    meta: { requiresAuth: true },
  },
  {
    path: '/incoming-feeds',
    name: 'incoming-feeds',
    component: IncomingFeedsManagement,
    meta: { requiresAuth: true },
  },
  {
    path: '/incoming-feeds/ping',
    name: 'ping-requests',
    component: PingRequestsManagement,
    meta: { requiresAuth: true },
  },
  {
    path: '/outgoing-feeds',
    name: 'outgoing-feeds',
    component: OutgoingFeedsManagement,
    meta: { requiresAuth: true },
  },
  {
    path: '/outgoing-feeds/ping',
    name: 'outgoing-feeds-ping',
    component: OutgoingFeedsPingManagement,
    meta: { requiresAuth: true },
  },
  {
    path: '/record-search',
    name: 'record-search',
    component: RecordSearch,
    meta: { requiresAuth: true },
  },
  {
    path: '/record-search/outgoing',
    name: 'outgoing-record-search',
    component: OutgoingRecordSearch,
    meta: { requiresAuth: true },
  },
  {
    path: '/',
    redirect: '/dashboard',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  const auth = useAuth();
  
  if (to.meta.requiresAuth && !auth.isAuthenticated()) {
    next('/login');
  } else if (to.path === '/login' && auth.isAuthenticated()) {
    next('/dashboard');
  } else {
    next();
  }
});

export default router;

<template>
  <nav class="navbar navbar-default navbar-custom">
    <div class="container-fluid">
      <div class="navbar-header">
        <button
          type="button"
          class="navbar-toggle collapsed"
          data-toggle="collapse"
          data-target="#primary-navbar"
          aria-expanded="false"
        >
          <span class="sr-only">Toggle navigation</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <router-link class="navbar-brand" to="/dashboard">
          <img
            alt="Company logo"
            height="20"
            src="/images/Q-isolated.jpg"
            width="20"
          />
        </router-link>
      </div>

      <div class="collapse navbar-collapse" id="primary-navbar">
        <ul class="nav navbar-nav">
          <li :class="{ active: $route.name === 'dashboard' }">
            <router-link to="/dashboard">Dashboard</router-link>
          </li>
          <li :class="{ active: $route.name === 'companies' }">
            <router-link to="/companies">Companies</router-link>
          </li>
          <li :class="{ active: $route.name === 'incoming-feeds' || $route.name === 'ping-requests' }">
            <router-link to="/incoming-feeds">Incoming Feeds</router-link>
          </li>
          <li :class="{ active: $route.name === 'outgoing-feeds' || $route.name === 'outgoing-feeds-ping' || $route.name === 'outgoing-record-search' }">
            <router-link to="/outgoing-feeds">Outgoing Feeds</router-link>
          </li>
          <li class="dropdown" :class="{ active: $route.name === 'verticals' || $route.name === 'fields' || $route.name === 'users' || $route.name === 'record-search' || $route.name === 'outgoing-record-search' }">
            <a
              href="#"
              class="dropdown-toggle"
              data-toggle="dropdown"
              role="button"
              aria-haspopup="true"
              aria-expanded="false"
            >
              Admin <span class="caret"></span>
            </a>
            <ul class="dropdown-menu">
              <li>
                <router-link to="/verticals">Vertical Management</router-link>
              </li>
              <li>
                <router-link to="/fields">Field Management</router-link>
              </li>
              <li>
                <router-link to="/users">User Management</router-link>
              </li>
              <li>
                <router-link to="/record-search">Record Search (Incoming)</router-link>
              </li>
              <li>
                <router-link to="/record-search/outgoing">Record Search (Outgoing)</router-link>
              </li>
            </ul>
          </li>
        </ul>
        <ul class="nav navbar-nav navbar-right">
          <li>
            <a href="#" @click.prevent="handleLogout">Log Out</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</template>

<script>
import { useRouter } from 'vue-router';
import { useAuth } from '../composables/useAuth';

export default {
  name: 'Navigation',
  setup() {
    const router = useRouter();
    const { logout } = useAuth();

    const handleLogout = async () => {
      await logout();
      router.push('/login');
    };

    return {
      handleLogout,
    };
  },
};
</script>

<style scoped>
.navbar-custom {
  background-color: #072f5f;
  border-color: #072f5f;
  background-image: linear-gradient(to top, #072f5f 1%, #072f5f 25%, #072f5f 50%);
}

.navbar-custom .navbar-nav > li > a,
.navbar-custom .navbar-nav > li > a:hover {
  color: #fff;
}

.navbar-custom .navbar-nav > .active > a,
.navbar-custom .navbar-nav > .active > a:hover {
  background-color: rgba(255, 255, 255, 0.1);
  color: #fff;
}
</style>

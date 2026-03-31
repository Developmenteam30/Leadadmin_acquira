<template>
  <div>
    <Navigation />
    <div class="container-fluid">
      <h2>Outgoing Feeds</h2>
      <div class="row">
        <div class="col-md-9">
          <div style="clear: both; margin-bottom: 15px;">
            <div class="row">
              <div class="col-md-10">
                <QuickJump
                  :start="filters.statsStart"
                  :end="filters.statsEnd"
                  @update:start="filters.statsStart = $event"
                  @update:end="filters.statsEnd = $event"
                  @change="fetchFeeds"
                />
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="pull-right" style="margin-bottom: 15px;">
            <select v-model="filters.status" class="form-control" @change="fetchFeeds" style="display: inline-block; width: auto;">
              <option value="active">Show active feeds</option>
              <option value="hidden">Show hidden feeds</option>
              <option value="retired">Show retired feeds</option>
              <option value="">Show all feeds</option>
            </select>
          </div>
        </div>
      </div>

      <p>
        <button
          type="button"
          class="btn btn-primary"
          @click="openAddModal"
        >
          Add a new feed
        </button>
        <router-link to="/outgoing-feeds/ping" class="btn btn-primary" style="margin-left: 10px;">
          Outgoing Feeds (Ping)
        </router-link>
        <router-link to="/record-search/outgoing" class="btn btn-default" style="margin-left: 10px;">
          Outgoing Record Search
        </router-link>
      </p>

      <div v-if="loading" class="text-center">
        <p>Loading...</p>
      </div>

      <div v-else-if="companyGroups.length === 0">
        <p>No outgoing feeds found.</p>
      </div>

      <div v-else>
        <h4>Outgoing Email Feeds</h4>
        <table class="table table-bordered table-condensed table-striped">
          <thead>
            <tr class="bgGray">
              <th class="outgoing-col-large" colspan="4">Company</th>
              <th class="outgoing-col-small" colspan="2">Total Feeds</th>
              <th class="outgoing-col-small text-right">Total Accepted</th>
              <th class="outgoing-col-small text-right">Total Rejected</th>
              <th class="outgoing-col-small text-right">Total Queued</th>
              <th class="outgoing-col-small">Options</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(company, index) in companyGroups" :key="company.idCompany">
              <tr class="custom-master">
                <td colspan="4">
                  <strong>{{ company.name }}</strong>
                </td>
                <td colspan="2">
                  {{ company.totalFeeds }} ({{ company.totalActive }} Active)
                </td>
                <td class="text-right record-search-link-cell">
                  <router-link :to="outgoingRecordSearchLink(company.idCompany, null, 'accepted')">
                    <strong>{{ formatNumber(company.totalAccepted) }}</strong>
                  </router-link>
                </td>
                <td class="text-right record-search-link-cell">
                  <router-link :to="outgoingRecordSearchLink(company.idCompany, null, 'rejected')">
                    <strong>{{ formatNumber(company.totalRejected) }}</strong>
                  </router-link>
                </td>
                <td class="text-right">
                  <strong>{{ formatNumber(company.totalQueued) }}</strong>
                </td>
                <td class="text-center">
                  <button
                    type="button"
                    class="btn btn-sm btn-default"
                    @click="toggleCompanyFeeds(company.idCompany)"
                  >
                    {{ expandedCompanies[company.idCompany] ? 'Hide Feeds' : 'Show Feeds' }}
                  </button>
                </td>
              </tr>
              <template v-if="expandedCompanies[company.idCompany]">
                <tr
                  v-for="feed in company.feeds"
                  :key="feed.idFeedOut"
                  class="bg-gray feed-toggle"
                >
                  <td>{{ feed.idFeedOut }}</td>
                  <td>
                    <strong
                      :class="{
                        'status-active': feed.status === 'active',
                        'status-hidden': feed.status === 'hidden',
                        'status-retired': feed.status === 'retired',
                      }"
                    >
                      {{ feed.label }}
                    </strong>
                  </td>
                  <td>{{ feed.description }}</td>
                  <td>{{ feed.status }}</td>
                  <td>{{ feed.status }}</td>
                  <td>
                    <label class="switch">
                      <input
                        type="checkbox"
                        :checked="feed.status === 'active'"
                        @change="toggleStatus(feed.idFeedOut, $event.target.checked)"
                      />
                      <span class="slider"></span>
                    </label>
                  </td>
                  <td class="text-right record-search-link-cell">
                    <router-link :to="outgoingRecordSearchLink(null, feed.idFeedOut, 'accepted')">
                      {{ formatNumber(feed.accepted) }}
                    </router-link>
                  </td>
                  <td class="text-right record-search-link-cell">
                    <router-link :to="outgoingRecordSearchLink(null, feed.idFeedOut, 'rejected')">
                      {{ formatNumber(feed.rejected) }}
                    </router-link>
                  </td>
                  <td class="text-right">{{ formatNumber(feed.queuedCount) }}</td>
                  <td class="text-center">
                    <div class="btn-group" :class="{ open: openDropdownFeedId === feed.idFeedOut }">
                      <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        @click="openEditModal(feed)"
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        class="btn btn-sm btn-primary dropdown-toggle"
                        @click.stop="toggleFeedDropdown(feed.idFeedOut)"
                        aria-haspopup="true"
                        :aria-expanded="openDropdownFeedId === feed.idFeedOut"
                      >
                        <span class="caret"></span>
                        <span class="sr-only">Toggle Dropdown</span>
                      </button>
                      <ul
                        v-show="openDropdownFeedId === feed.idFeedOut"
                        class="dropdown-menu dropdown-menu-right"
                        @click="openDropdownFeedId = null"
                      >
                        <li><a href="#" @click.prevent="openShowPopulationsModal(feed)">Show populations</a></li>
                        <li><a href="#" @click.prevent="openDuplicateFeedModal(feed)">Duplicate feed</a></li>
                        <li><a href="#" @click.prevent="openSendTestRecordModal(feed)">Send test record</a></li>
                        <li><a href="#" @click.prevent="openQueuePreviewModal(feed)">Queue preview</a></li>
                        <li><a href="#" @click.prevent="openClearQueueModal(feed)">Clear queue</a></li>
                        <li><a href="#" @click.prevent="openUrlReportModal(feed)">URL report</a></li>
                        <li><a href="#" @click.prevent="openImportDataModal(feed)">Import data</a></li>
                        <li><a href="#" @click.prevent="openUploadDataModal(feed)">Upload data</a></li>
                        <li><a href="#" @click.prevent="openExportDataModal(feed)">Export data</a></li>
                        <li><a href="#" @click.prevent="openRetryRejectionsModal(feed)">Retry rejections</a></li>
                      </ul>
                    </div>
                  </td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Add Feed Modal -->
      <div
        class="modal fade"
        id="newFeedModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="newFeedModalTitle"
      >
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button
                type="button"
                class="close"
                @click="closeAddModal"
                aria-label="Close"
              >
                <span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title" id="newFeedModalTitle">
                Add a new feed
              </h4>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
              <OutboundFeedForm
                ref="addFeedForm"
                :feed="newFeed"
                :companies="companies"
                :availableFields="availableFields"
                :feedCategories="feedCategories"
                :feedTypes="feedTypes"
                :timezones="timezones"
                :staffUsers="staffUsers"
                @update:feed="updateNewFeed"
              />
              <div v-if="addError" class="alert alert-danger">
                {{ addError }}
              </div>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-default"
                @click="closeAddModal"
              >
                Close
              </button>
              <button
                type="button"
                class="btn btn-primary"
                @click="handleAddFeed"
                :disabled="adding"
              >
                {{ adding ? 'Adding...' : 'Add feed' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Feed Modal -->
      <div
        class="modal fade"
        id="editFeedModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="editFeedModalTitle"
      >
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button
                type="button"
                class="close"
                @click="closeEditModal"
                aria-label="Close"
              >
                <span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title" id="editFeedModalTitle">
                Edit feed
              </h4>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
              <div v-if="!editingFeed.idFeedOut && !editError" class="text-center" style="padding: 20px;">
                <p>Loading feed data...</p>
              </div>
              <template v-else-if="editingFeed.idFeedOut">
                <OutboundFeedForm
                  :key="editingFeed.idFeedOut"
                  ref="editFeedForm"
                  :feed="editingFeed"
                  :companies="companies"
                  :availableFields="availableFields"
                  :feedCategories="feedCategories"
                  :feedTypes="feedTypes"
                  :timezones="timezones"
                  :staffUsers="staffUsers"
                  :isEdit="true"
                  @update:feed="updateEditingFeed"
                />
              </template>
              <div v-if="editError" class="alert alert-danger">
                {{ editError }}
              </div>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-default"
                @click="closeEditModal"
              >
                Close
              </button>
              <button
                type="button"
                class="btn btn-primary"
                @click="handleUpdateFeed"
                :disabled="updating"
              >
                {{ updating ? 'Saving...' : 'Save changes' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Outgoing Feed Modals (Teleport to body for proper stacking) -->
      <Teleport to="body">
        <!-- Populations Modal -->
        <div
          v-show="populationsModal.show"
          class="outgoing-feed-modal"
          tabindex="-1"
          @click.self="closePopulationsModal"
        >
          <div class="modal-dialog modal-lg" style="max-width: 95%;" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Population Settings</h4>
                <button type="button" class="close" @click="closePopulationsModal">&times;</button>
              </div>
              <div class="modal-body">
                <div v-if="populationsModal.idFeedOut" class="mb-3">
                  <strong>Feed ID:</strong> {{ populationsModal.idFeedOut }}
                  <span class="mx-2">|</span>
                  <strong>Label:</strong> {{ populationsModal.feedLabel || '' }}
                  <span class="mx-2">|</span>
                  <strong>Description:</strong> {{ populationsModal.feedDescription || '' }}
                </div>
                <div v-if="populationsLoading" class="text-center">Loading...</div>
                <template v-else>
                  <p v-show="false">
                    <button type="button" class="btn btn-primary btn-sm" @click="openAddPopulationModal">
                      Add a new population parameter
                    </button>
                  </p>
                  <div v-if="populations.length" class="table-responsive">
                    <table class="table table-bordered table-condensed table-striped">
                      <thead>
                        <tr class="bgGray">
                          <th>Order</th>
                          <th>Populating Feed</th>
                          <th>Population Status</th>
                          <th>Filtering By URL</th>
                          <th>URL Filter Settings</th>
                          <th>Filtering By Email</th>
                          <th>Email Filter Settings</th>
                          <th>Filtering By Listcode</th>
                          <th>Listcode Filter Settings</th>
                          <th>Force URLs</th>
                          <th>Force URL Settings</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="p in populations" :key="p.idAssoc">
                          <td>{{ p.order ?? '—' }}</td>
                          <td>{{ p.populatingFeed || p.inboundLabel }}</td>
                          <td>
                            <label class="switch">
                              <input
                                type="checkbox"
                                :checked="p.enabled === '1'"
                                @change="togglePopulation(p.idAssoc)"
                              />
                              <span class="slider"></span>
                            </label>
                            <span class="ml-1">{{ p.enabled === '1' ? 'Populating' : 'Disabled' }}</span>
                          </td>
                          <td>{{ p.filterTypeUrlDisplay }}</td>
                          <td>{{ p.filterUrlDisplay }}</td>
                          <td>{{ p.filterTypeEmailDisplay }}</td>
                          <td>{{ p.filterEmailDisplay }}</td>
                          <td>{{ p.filterTypeListcodeDisplay }}</td>
                          <td>{{ p.filterListcodeDisplay }}</td>
                          <td>{{ p.forceUrlDisplay }}</td>
                          <td style="white-space: pre-wrap;">{{ p.forceUrlListDisplay }}</td>
                          <td>
                            <button type="button" class="btn btn-xs btn-default" @click="openEditPopulationModal(p)">Edit</button>
                            <button type="button" class="btn btn-xs btn-danger" @click="deletePopulation(p.idAssoc)">Delete</button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <p v-else>No populations found.</p>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Add/Edit Population Modal (legacy-style) -->
        <div
          v-show="addPopulationModal.show"
          class="outgoing-feed-modal add-population-modal"
          tabindex="-1"
          @click.self="closeAddPopulationModal"
        >
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">{{ addPopulationModal.editId ? 'Edit population' : 'Add a new population parameter' }}</h4>
                <button type="button" class="close" @click="closeAddPopulationModal" aria-label="Close">&times;</button>
              </div>
              <div class="modal-body">
                <!-- Incoming Feed (To Populate From) -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Incoming Feed (To Populate From)</div>
                  <div class="add-pop-content">
                    <div class="add-pop-radios">
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.populationType" value="individual" /> Individual Feed Population</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.populationType" value="category" /> Feed Category Population</label>
                    </div>
                    <template v-if="addPopulationModal.populationType === 'individual'">
                      <SearchableMultiselect
                        v-if="!addPopulationModal.editId"
                        v-model="addPopulationModal.idFeedIn"
                        :options="availableInboundFeedsForPopulation"
                        value-key="idFeedIn"
                        label-key="label"
                        placeholder="Search incoming feeds..."
                      />
                      <p v-else class="add-pop-readonly">Populating from: {{ addPopulationModal.editFeedLabel || '—' }}</p>
                      <small v-if="!addPopulationModal.editId" class="text-muted">Type to search. Each selected feed will create a separate population.</small>
                    </template>
                    <select v-else v-model="addPopulationModal.feedCategory" class="form-control add-pop-select">
                      <option value="">Select a category</option>
                      <option v-for="c in feedCategoriesForPopulation" :key="c" :value="c">{{ c }}</option>
                    </select>
                  </div>
                </div>

                <!-- URL Filter Options -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">URL Filter Options</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Using the 'Accept' option, urls that are listed here are the only ones that will be accepted into the feed. Using the 'Reject' option, all urls will be accepted, except the ones listed here.</p>
                    <div class="add-pop-radios">
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.filterTypeUrl" value="" /> Disabled</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.filterTypeUrl" value="accept" /> Accept</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.filterTypeUrl" value="reject" /> Reject</label>
                    </div>
                    <input v-if="addPopulationModal.filterTypeUrl" v-model="addPopulationModal.filterUrl" type="text" class="form-control add-pop-input" placeholder="URLs (semicolon-separated)" />
                  </div>
                </div>

                <!-- Email Filter Options -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Email Filter Options</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Using the 'Accept' option, email domains that are listed here are the only ones that will be accepted into the feed. Using the 'Reject' option, all email domains will be accepted, except the ones listed here.</p>
                    <div class="add-pop-radios">
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.filterTypeEmail" value="" /> Disabled</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.filterTypeEmail" value="accept" /> Accept</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.filterTypeEmail" value="reject" /> Reject</label>
                    </div>
                    <input v-if="addPopulationModal.filterTypeEmail" v-model="addPopulationModal.filterEmail" type="text" class="form-control add-pop-input" placeholder="Email domains (semicolon-separated)" />
                  </div>
                </div>

                <!-- Listcode Filter Options -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Listcode Filter Options</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Using the 'Accept' option, listcodes that are listed here are the only ones that will be accepted into the feed. Using the 'Reject' option, all listcodes will be accepted, except the ones listed here.</p>
                    <div class="add-pop-radios">
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.filterTypeListcode" value="" /> Disabled</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.filterTypeListcode" value="accept" /> Accept</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.filterTypeListcode" value="reject" /> Reject</label>
                    </div>
                    <input v-if="addPopulationModal.filterTypeListcode" v-model="addPopulationModal.filterListcode" type="text" class="form-control add-pop-input" placeholder="Listcodes (semicolon-separated)" />
                  </div>
                </div>

                <!-- Force URL Options -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Force URL Options</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Utilizing 'URL Forcing' changes the url listed in the incoming feed to a completely different URL for use in the outgoing feed.</p>
                    <div class="add-pop-radios">
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.forceUrl" value="0" /> Disabled</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addPopulationModal.forceUrl" value="1" /> Enabled</label>
                    </div>
                    <input v-if="addPopulationModal.forceUrl === '1'" v-model="addPopulationModal.forceUrlList" type="text" class="form-control add-pop-input" placeholder="Force URL mappings (semicolon-separated)" />
                  </div>
                </div>

                <!-- Queue Type -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Queue Type</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Incoming records will be sent to this provider in REAL TIME as they come in. Do not use this option unless authorized. Most feeds have this option disabled.</p>
                    <div class="add-pop-queue-options">
                      <label class="add-pop-queue-opt"><input type="radio" v-model="addPopulationModal.queueType" value="livedata" /> Live Data (leads sent in real-time) [DEFAULT]</label>
                      <label class="add-pop-queue-opt"><input type="radio" v-model="addPopulationModal.queueType" value="queue" /> Standard Queue</label>
                      <label class="add-pop-queue-opt"><input type="radio" v-model="addPopulationModal.queueType" value="waterfall" /> Waterfall Live Standard (attempt each vendor in descending priority order; stop after the first accepted response)</label>
                      <label class="add-pop-queue-opt"><input type="radio" v-model="addPopulationModal.queueType" value="waterfallLimit" /> Waterfall Limit &amp; Queue (attempt vendors in priority order and queue; only skip to the next after the feed limits are hit)</label>
                      <label class="add-pop-queue-opt"><input type="radio" v-model="addPopulationModal.queueType" value="waterfallLimitLive" /> Waterfall Limit Live (attempt vendors in real-time in priority order; only skip to the next after the feed limits are hit)</label>
                    </div>
                  </div>
                </div>

                <!-- Waterfall Priority -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Waterfall Priority</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Only applies if the Queue Type setting above is set to "Waterfall" or "Waterfall Limit". Use any number from 0 to 65,535. A higher number means higher priority in the waterfall.</p>
                    <input v-model.number="addPopulationModal.waterfallPriority" type="number" min="0" max="65535" class="form-control add-pop-input add-pop-input-sm" placeholder="Waterfall Priority" />
                  </div>
                </div>

                <!-- Population Start Date -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Population Start Date</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">If a value is filled in here, then records will not start populating this queue until midnight of the date provided. When using this feature, it is recommended to turn the "Queueing" option ON, because if the "Queueing" option is set to off, then no records will be queued at all, even if the start date below passes.</p>
                    <input v-model="addPopulationModal.startDate" type="date" class="form-control add-pop-input add-pop-input-sm" />
                  </div>
                </div>

                <div v-if="addPopulationModal.editId" class="add-pop-row">
                  <div class="add-pop-heading"></div>
                  <div class="add-pop-content">
                    <label class="add-pop-checkbox"><input v-model="addPopulationModal.enabled" type="checkbox" value="1" /> Enabled</label>
                  </div>
                </div>

                <div v-if="addPopulationModalError" class="alert alert-danger">{{ addPopulationModalError }}</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" @click="closeAddPopulationModal">Close</button>
                <button type="button" class="btn btn-success" @click="savePopulation" :disabled="addPopulationSaving">
                  {{ addPopulationSaving ? 'Saving...' : (addPopulationModal.editId ? 'Save' : 'Add population') }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Queue Preview Modal -->
        <div v-show="queuePreviewModal.show" class="outgoing-feed-modal" tabindex="-1" @click.self="closeQueuePreviewModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Queue Preview – {{ queuePreviewModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeQueuePreviewModal">&times;</button>
              </div>
              <div class="modal-body">
                <div v-if="queuePreviewLoading">Loading...</div>
                <template v-else-if="queuePreviewData.length">
                  <table class="table table-bordered table-condensed table-striped">
                    <thead><tr><th>Inbound Date</th><th>Count</th></tr></thead>
                    <tbody>
                      <tr v-for="(row, i) in queuePreviewData" :key="i">
                        <td>{{ row.date }}</td>
                        <td class="text-right">{{ formatNumber(row.cnt) }}</td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr><td>GRAND TOTAL</td><td class="text-right">{{ formatNumber(queuePreviewTotal) }}</td></tr>
                    </tfoot>
                  </table>
                </template>
                <p v-else>No queued records found.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Clear Queue Modal -->
        <div v-show="clearQueueModal.show" class="outgoing-feed-modal" tabindex="-1" @click.self="closeClearQueueModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Clear queued records – {{ clearQueueModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeClearQueueModal">&times;</button>
              </div>
              <div class="modal-body">
                <p>This will submit a job to clear all queued records for this feed. The job will run asynchronously.</p>
                <div v-if="clearQueueMessage" class="alert" :class="clearQueueMessage.success ? 'alert-success' : 'alert-danger'">{{ clearQueueMessage.text }}</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" @click="closeClearQueueModal">Close</button>
                <button type="button" class="btn btn-danger" @click="submitClearQueue" :disabled="clearQueueSending">{{ clearQueueSending ? 'Submitting...' : 'Clear Queue' }}</button>
              </div>
            </div>
          </div>
        </div>

        <!-- URL Report Modal -->
        <div v-show="urlReportModal.show" class="outgoing-feed-modal" tabindex="-1" @click.self="closeUrlReportModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">URL Report – {{ urlReportModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeUrlReportModal">&times;</button>
              </div>
              <div class="modal-body">
                <p class="text-muted">Period goes from midnight of the first date to midnight of the second date. Leave blank for all time.</p>
                <div class="form-group">
                  <label>Date range</label>
                  <input v-model="urlReportModal.dateStart" type="date" class="form-control" style="display: inline-block; width: auto;" /> to
                  <input v-model="urlReportModal.dateEnd" type="date" class="form-control" style="display: inline-block; width: auto;" />
                </div>
                <div class="form-group">
                  <label>URLs (leave empty for all)</label>
                  <select v-model="urlReportModal.urlList" multiple class="form-control" style="min-height: 80px;">
                    <option v-for="u in urlReportUrlList" :key="u.url" :value="u.url">{{ u.url }} ({{ u.date }})</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Count By</label>
                  <select v-model="urlReportModal.breakdown" class="form-control" style="width: auto;">
                    <option value="day">Day</option>
                    <option value="month">Month</option>
                    <option value="year">Year</option>
                    <option value="total">Total</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Group By</label>
                  <select v-model="urlReportModal.group" class="form-control" style="width: auto;">
                    <option value="date">Date</option>
                    <option value="url">URL</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Sort by</label>
                  <select v-model="urlReportModal.sort" class="form-control" style="width: auto;">
                    <option value="date">Date</option>
                    <option value="url">URL</option>
                    <option value="count">Count</option>
                  </select>
                </div>
                <button type="button" class="btn btn-primary" @click="runUrlReport" :disabled="urlReportLoading">{{ urlReportLoading ? 'Loading...' : 'Run Report' }}</button>
                <div v-if="urlReportResults.length" class="mt-3">
                  <table class="table table-bordered table-condensed table-striped">
                    <thead><tr><th>URL</th><th>Date</th><th>Accepted</th><th>Rejected</th></tr></thead>
                    <tbody>
                      <tr v-for="(r, i) in urlReportResults" :key="i">
                        <td>{{ r.url }}</td>
                        <td>{{ r.date }}</td>
                        <td>{{ formatNumber(r.accepted) }}</td>
                        <td>{{ formatNumber(r.rejected) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Import Data Modal -->
        <div v-show="importDataModal.show" class="outgoing-feed-modal" tabindex="-1" @click.self="closeImportDataModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Import Legacy Data – {{ importDataModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeImportDataModal">&times;</button>
              </div>
              <div class="modal-body">
                <p class="text-muted">Import records from the legacy system. Select a date range within the last 6 months.</p>
                <div class="form-group">
                  <label>Date range</label>
                  <input v-model="importDataModal.dateStart" type="date" class="form-control" style="display: inline-block; width: auto;" /> to
                  <input v-model="importDataModal.dateEnd" type="date" class="form-control" style="display: inline-block; width: auto;" />
                </div>
                <div v-if="importDataModal.message" class="alert" :class="importDataModal.success ? 'alert-success' : 'alert-danger'">{{ importDataModal.message }}</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" @click="closeImportDataModal">Close</button>
                <button type="button" class="btn btn-primary" @click="submitImportData" :disabled="importDataSending">{{ importDataSending ? 'Importing...' : 'Import Data' }}</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Upload Data Modal -->
        <div v-show="uploadDataModal.show" class="outgoing-feed-modal" tabindex="-1" @click.self="closeUploadDataModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Upload Legacy Data – {{ uploadDataModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeUploadDataModal">&times;</button>
              </div>
              <div class="modal-body">
                <p class="text-muted">Upload a CSV or Excel file to add records to the outbound queue.</p>
                <div class="form-group">
                  <label>File</label>
                  <input ref="uploadFileInput" type="file" accept=".csv,.xlsx,.xls" class="form-control" @change="onUploadFileSelect" />
                </div>
                <div v-if="uploadDataModal.message" class="alert" :class="uploadDataModal.success ? 'alert-success' : 'alert-danger'">{{ uploadDataModal.message }}</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" @click="closeUploadDataModal">Close</button>
                <button type="button" class="btn btn-primary" @click="submitUploadData" :disabled="uploadDataSending || !uploadDataModal.file">{{ uploadDataSending ? 'Uploading...' : 'Upload Data' }}</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Export Data Modal -->
        <div v-show="exportDataModal.show" class="outgoing-feed-modal" tabindex="-1" @click.self="closeExportDataModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Export Data – {{ exportDataModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeExportDataModal">&times;</button>
              </div>
              <div class="modal-body">
                <p class="text-muted">Period goes from midnight of the first date to midnight of the second date.</p>
                <div v-if="exportColumns.length" class="form-group">
                  <label>Columns</label>
                  <div class="mb-2">
                    <button type="button" class="btn btn-xs btn-default" @click="exportColumns.forEach(c => { c.checked = true })">Check All</button>
                    <button type="button" class="btn btn-xs btn-default" @click="exportColumns.forEach(c => { c.checked = false })">Uncheck All</button>
                  </div>
                  <div style="max-height: 150px; overflow-y: auto;">
                    <label v-for="c in exportColumns" :key="c.fieldName" class="checkbox-label d-block">
                      <input v-model="c.checked" type="checkbox" /> {{ c.fieldName }}
                    </label>
                  </div>
                </div>
                <div class="form-group">
                  <label>Date range</label>
                  <input v-model="exportDataModal.dateStart" type="date" class="form-control" style="display: inline-block; width: auto;" /> to
                  <input v-model="exportDataModal.dateEnd" type="date" class="form-control" style="display: inline-block; width: auto;" />
                </div>
                <div class="form-group">
                  <label>Limit (optional)</label>
                  <input v-model="exportDataModal.limit" type="number" min="1" class="form-control" style="width: 120px;" placeholder="No limit" />
                </div>
                <div class="form-group">
                  <label><input v-model="exportDataModal.includeRejects" type="checkbox" /> Include rejected records</label>
                </div>
                <div v-if="exportDataModal.message" class="alert" :class="exportDataModal.success ? 'alert-success' : 'alert-info'">{{ exportDataModal.message }}</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" @click="closeExportDataModal">Close</button>
                <button type="button" class="btn btn-primary" @click="submitExportData" :disabled="exportDataSending">{{ exportDataSending ? 'Exporting...' : 'Export Data' }}</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Retry Rejections Modal -->
        <div v-show="retryRejectionsModal.show" class="outgoing-feed-modal" tabindex="-1" @click.self="closeRetryRejectionsModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Retry Rejections – {{ retryRejectionsModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeRetryRejectionsModal">&times;</button>
              </div>
              <div class="modal-body">
                <p class="text-muted">Re-add rejected records to the outbound queue. Select a date range within the last 6 months.</p>
                <div class="form-group">
                  <label>Date range</label>
                  <input v-model="retryRejectionsModal.dateStart" type="date" class="form-control" style="display: inline-block; width: auto;" /> to
                  <input v-model="retryRejectionsModal.dateEnd" type="date" class="form-control" style="display: inline-block; width: auto;" />
                </div>
                <div v-if="retryRejectionsModal.message" class="alert" :class="retryRejectionsModal.success ? 'alert-success' : 'alert-danger'">{{ retryRejectionsModal.message }}</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" @click="closeRetryRejectionsModal">Close</button>
                <button type="button" class="btn btn-primary" @click="submitRetryRejections" :disabled="retryRejectionsSending">{{ retryRejectionsSending ? 'Submitting...' : 'Submit' }}</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Send Test Record Modal -->
        <div
          v-show="testRecordModal.show"
          class="outgoing-feed-modal"
          tabindex="-1"
          @click.self="closeTestRecordModal"
        >
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Send a test record – {{ testRecordModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeTestRecordModal">&times;</button>
              </div>
              <div class="modal-body">
              <p class="text-muted">Use default values or change them for testing.</p>
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Email</label>
                    <input v-model="testRecordModal.email" type="email" class="form-control" />
                  </div>
                  <div class="form-group">
                    <label>First Name</label>
                    <input v-model="testRecordModal.fname" type="text" class="form-control" />
                  </div>
                  <div class="form-group">
                    <label>Last Name</label>
                    <input v-model="testRecordModal.lname" type="text" class="form-control" />
                  </div>
                  <div class="form-group">
                    <label>Address</label>
                    <input v-model="testRecordModal.addr" type="text" class="form-control" />
                  </div>
                  <div class="form-group">
                    <label>City</label>
                    <input v-model="testRecordModal.city" type="text" class="form-control" />
                  </div>
                  <div class="form-group">
                    <label>State</label>
                    <input v-model="testRecordModal.state" type="text" class="form-control" />
                  </div>
                  <div class="form-group">
                    <label>Zip</label>
                    <input v-model="testRecordModal.zip" type="text" class="form-control" />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Cell Phone</label>
                    <input v-model="testRecordModal.cellphone" type="text" class="form-control" />
                  </div>
                  <div class="form-group">
                    <label>Landline</label>
                    <input v-model="testRecordModal.landline" type="text" class="form-control" />
                  </div>
                  <div class="form-group">
                    <label>Gender</label>
                    <select v-model="testRecordModal.gender" class="form-control">
                      <option value="M">M</option>
                      <option value="F">F</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Country</label>
                    <input v-model="testRecordModal.country" type="text" class="form-control" />
                  </div>
                </div>
              </div>
              <div v-if="testRecordResult" class="mt-3" :class="testRecordResult.success ? 'alert alert-success' : 'alert alert-danger'">
                <strong>{{ testRecordResult.success ? 'Success' : 'Failed' }}</strong>
                <pre v-if="testRecordResult.text" style="max-height: 200px; overflow: auto;">{{ testRecordResult.text }}</pre>
              </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" @click="closeTestRecordModal">Close</button>
                <button type="button" class="btn btn-primary" @click="sendTestRecord" :disabled="testRecordSending">
                  {{ testRecordSending ? 'Sending...' : 'Send Test' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, nextTick, reactive, watch, computed } from 'vue';
import axios from 'axios';
import Navigation from './Navigation.vue';
import OutboundFeedForm from './OutboundFeedForm.vue';
import QuickJump from './QuickJump.vue';
import SearchableMultiselect from './SearchableMultiselect.vue';

export default {
  name: 'OutgoingFeedsManagement',
  components: {
    Navigation,
    OutboundFeedForm,
    QuickJump,
    SearchableMultiselect,
  },
  setup() {
    const companyGroups = ref([]);
    const companies = ref([]);
    const availableFields = ref([]);
    const feedCategories = ref({});
    const feedTypes = ref({});
    const timezones = ref([]);
    const staffUsers = ref([]);
    const loading = ref(false);
    const adding = ref(false);
    const updating = ref(false);
    const addError = ref('');
    const editError = ref('');
    const expandedCompanies = ref({});
    const openDropdownFeedId = ref(null);

    const toggleFeedDropdown = (idFeedOut) => {
      openDropdownFeedId.value = openDropdownFeedId.value === idFeedOut ? null : idFeedOut;
    };
    const defaultProcessingSchedule = () => ({
      sun: { enabled: true, startTime: '', endTime: '' },
      mon: { enabled: true, startTime: '', endTime: '' },
      tue: { enabled: true, startTime: '', endTime: '' },
      wed: { enabled: true, startTime: '', endTime: '' },
      thu: { enabled: true, startTime: '', endTime: '' },
      fri: { enabled: true, startTime: '', endTime: '' },
      sat: { enabled: true, startTime: '', endTime: '' },
    });
    const editingFeed = reactive({
      idFeedOut: null,
      label: '',
      description: '',
      idCompany: '',
      feedType: 'curlPOST',
      postUrl: '',
      timezone: 'UTC',
      feedCategory: 'email',
      status: 'active',
      cron: '0',
      cronTiming: 1,
      successString: '',
      throttle: 100,
      dailyLimit: '',
      delay: '',
      delayDump: '0',
      notifyThresholdCount: '0',
      notifyThresholdTime: '',
      notifyThresholdDays: [],
      revenuePerLead: '',
      launchDate: '',
      costPerLeadOverride: '',
      salesperson: '',
      leadStatus: '',
      staticFields: [],
      varFields: [],
      valueMap: [],
      urlassignments: [],
      xmlDTD: '',
      processingSchedule: defaultProcessingSchedule(),
      prepingEnabled: '0',
      prepingUrl: '',
      prepingHttpMethod: 'POST',
      prepingAuthType: 'none',
      prepingAuthValue: '',
    });

    const filters = reactive({
      status: 'active',
      feedCategory: 'email',
      statsStart: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      statsEnd: new Date().toISOString().split('T')[0],
    });

    const newFeed = reactive({
      label: '',
      description: '',
      idCompany: '',
      feedType: 'curlPOST',
      postUrl: '',
      timezone: 'America/New_York',
      feedCategory: 'email',
      responseType: 'realtime',
      webhookSecret: '',
      status: 'active',
      cron: '0',
      cronTiming: 1,
      successString: '',
      throttle: 100,
      dailyLimit: '',
      delay: '',
      delayDump: '0',
      notifyThresholdCount: '0',
      notifyThresholdTime: '',
      notifyThresholdDays: [],
      revenuePerLead: '',
      launchDate: '',
      costPerLeadOverride: '',
      salesperson: '',
      leadStatus: '',
      staticFields: [],
      varFields: [],
      valueMap: [],
      urlassignments: [],
      xmlDTD: '',
      processingSchedule: defaultProcessingSchedule(),
      prepingEnabled: '0',
      prepingUrl: '',
      prepingHttpMethod: 'POST',
      prepingAuthType: 'none',
      prepingAuthValue: '',
    });

    const formatNumber = (num) => {
      return new Intl.NumberFormat().format(num);
    };

    const fetchFeeds = async () => {
      loading.value = true;
      try {
        const params = {
          feedCategory: filters.feedCategory,
          statsStart: filters.statsStart,
          statsEnd: filters.statsEnd,
        };
        if (filters.status) {
          params.status = filters.status;
        }

        const response = await axios.get('/api/outbound-feeds', { params });
        if (response.data.status === 1) {
          companyGroups.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching feeds:', error);
        companyGroups.value = [];
      } finally {
        loading.value = false;
      }
    };

    const toggleCompanyFeeds = (companyId) => {
      expandedCompanies.value[companyId] = !expandedCompanies.value[companyId];
    };

    const toggleStatus = async (idFeedOut, enabled) => {
      try {
        const r = await axios.patch(`/api/outbound-feeds/${idFeedOut}/toggle-status`, {
          enabled: enabled,
        });
        if (r.data.status === 1) {
          await fetchFeeds();
        } else {
          alert(r.data.error || 'Failed to toggle feed status');
        }
      } catch (error) {
        console.error('Error toggling feed status:', error);
        alert(error.response?.data?.error || error.message || 'Failed to toggle feed status');
      }
    };

    const outgoingRecordSearchLink = (idCompany, idFeedOut, status) => {
      const query = {
        startDate: filters.statsStart,
        endDate: filters.statsEnd,
        status: status,
      };
      if (idCompany) query.idCompany = idCompany;
      if (idFeedOut) query.idFeedOut = idFeedOut;
      return { path: '/record-search/outgoing', query };
    };

    const openAddModal = async () => {
      addError.value = '';
      await nextTick();
      if (window.$ && window.$('#newFeedModal')) {
        window.$('#newFeedModal').modal('show');
      }
    };

    const closeAddModal = () => {
      addError.value = '';
      if (window.$ && window.$('#newFeedModal')) {
        window.$('#newFeedModal').modal('hide');
      }
    };

    const populationsModal = reactive({
      show: false,
      idFeedOut: null,
      feedLabel: '',
      feedDescription: '',
    });
    const populations = ref([]);
    const populationsLoading = ref(false);
    const inboundFeedsForPopulation = ref([]);
    const feedCategoriesForPopulation = ref([]);
    const availableInboundFeedsForPopulation = computed(() => {
      const usedIds = new Set(
        populations.value
          .filter((p) => p.populationType === 'individual' && p.idFeedIn)
          .map((p) => String(p.idFeedIn))
      );
      return inboundFeedsForPopulation.value.filter((f) => !usedIds.has(String(f.idFeedIn)));
    });

    const addPopulationModal = reactive({
      show: false,
      editId: null,
      editFeedLabel: '',
      populationType: 'individual',
      idFeedIn: [],
      feedCategory: '',
      filterTypeUrl: '',
      filterUrl: '',
      filterTypeEmail: '',
      filterEmail: '',
      filterTypeListcode: '',
      filterListcode: '',
      forceUrl: '0',
      forceUrlList: '',
      queueType: 'livedata',
      order: 1,
      waterfallPriority: 0,
      startDate: '',
      enabled: '1',
    });
    const addPopulationModalError = ref('');
    const addPopulationSaving = ref(false);

    const testRecordModal = reactive({
      show: false,
      idFeedOut: null,
      feedLabel: '',
      email: 'test@example.com',
      fname: 'Test',
      lname: 'User',
      addr: '123 Main St',
      city: 'New York',
      state: 'NY',
      zip: '10001',
      country: 'US',
      cellphone: '5551234567',
      landline: '5559876543',
      gender: 'M',
    });
    const testRecordResult = ref(null);
    const testRecordSending = ref(false);

    const queuePreviewModal = reactive({ show: false, idFeedOut: null, feedLabel: '' });
    const queuePreviewData = ref([]);
    const queuePreviewLoading = ref(false);
    const queuePreviewTotal = ref(0);

    const clearQueueModal = reactive({ show: false, idFeedOut: null, feedLabel: '' });
    const clearQueueMessage = ref(null);
    const clearQueueSending = ref(false);

    const urlReportModal = reactive({ show: false, idFeedOut: null, feedLabel: '', dateStart: '', dateEnd: '', urlList: [], breakdown: 'day', group: 'date', sort: 'date' });
    const urlReportUrlList = ref([]);
    const urlReportResults = ref([]);
    const urlReportLoading = ref(false);

    const importDataModal = reactive({ show: false, idFeedOut: null, feedLabel: '', dateStart: '', dateEnd: '', message: '', success: false });
    const importDataSending = ref(false);

    const uploadDataModal = reactive({ show: false, idFeedOut: null, feedLabel: '', file: null, message: '', success: false });
    const uploadDataSending = ref(false);
    const uploadFileInput = ref(null);

    const exportDataModal = reactive({ show: false, idFeedOut: null, feedLabel: '', dateStart: '', dateEnd: '', limit: '', includeRejects: false, message: '', success: false });
    const exportColumns = ref([]);
    const exportDataSending = ref(false);

    const retryRejectionsModal = reactive({ show: false, idFeedOut: null, feedLabel: '', dateStart: '', dateEnd: '', message: '', success: false });
    const retryRejectionsSending = ref(false);

    const fetchPopulations = async () => {
      if (!populationsModal.idFeedOut) return;
      populationsLoading.value = true;
      try {
        const r = await axios.get(`/api/outbound-feeds/${populationsModal.idFeedOut}/populations`);
        populations.value = (r.data.status === 1 ? r.data.data : []).map((p) => ({ ...p }));
      } catch (e) {
        populations.value = [];
      } finally {
        populationsLoading.value = false;
      }
    };

    const fetchInboundFeedsForPopulation = async () => {
      try {
        const r = await axios.get('/api/feed-populations/inbound-feeds');
        inboundFeedsForPopulation.value = r.data.status === 1 ? r.data.data : [];
      } catch (e) {
        inboundFeedsForPopulation.value = [];
      }
    };

    const fetchFeedCategoriesForPopulation = async () => {
      try {
        const r = await axios.get('/api/feed-populations/categories');
        feedCategoriesForPopulation.value = r.data.status === 1 ? r.data.data : [];
      } catch (e) {
        feedCategoriesForPopulation.value = [];
      }
    };

    watch(() => populationsModal.show, (show) => {
      if (show && populationsModal.idFeedOut) {
        fetchPopulations();
      }
    });

    const openShowPopulationsModal = async (feed) => {
      populationsModal.idFeedOut = feed.idFeedOut;
      populationsModal.feedLabel = feed.label;
      populationsModal.feedDescription = feed.description || '';
      populationsModal.show = true;
      await fetchInboundFeedsForPopulation();
      await fetchFeedCategoriesForPopulation();
    };

    const closePopulationsModal = () => {
      populationsModal.show = false;
      populationsModal.idFeedOut = null;
      populationsModal.feedLabel = '';
      populationsModal.feedDescription = '';
    };

    const togglePopulation = async (idAssoc) => {
      const p = populations.value.find((x) => x.idAssoc === idAssoc);
      if (!p) return;
      const prev = p.enabled;
      try {
        const r = await axios.patch(`/api/feed-populations/${idAssoc}/toggle`);
        if (r.data.status === 1) {
          p.enabled = r.data.data.enabled;
        } else {
          p.enabled = prev;
        }
      } catch (e) {
        p.enabled = prev;
        alert(e.response?.data?.error || e.message || 'Failed to toggle');
      }
    };

    const openAddPopulationModal = () => {
      addPopulationModal.editId = null;
      addPopulationModal.editFeedLabel = '';
      addPopulationModal.populationType = 'individual';
      addPopulationModal.idFeedIn = [];
      addPopulationModal.feedCategory = '';
      addPopulationModal.filterTypeUrl = '';
      addPopulationModal.filterUrl = '';
      addPopulationModal.filterTypeEmail = '';
      addPopulationModal.filterEmail = '';
      addPopulationModal.filterTypeListcode = '';
      addPopulationModal.filterListcode = '';
      addPopulationModal.forceUrl = '0';
      addPopulationModal.forceUrlList = '';
      addPopulationModal.queueType = 'livedata';
      addPopulationModal.order = (populations.value.length ? Math.max(...populations.value.map((p) => p.order || 0), 0) + 1 : 1);
      addPopulationModal.waterfallPriority = 0;
      addPopulationModal.startDate = '';
      addPopulationModal.enabled = '1';
      addPopulationModal.show = true;
      addPopulationModalError.value = '';
    };

    const openEditPopulationModal = (p) => {
      addPopulationModal.editId = p.idAssoc;
      addPopulationModal.editFeedLabel = p.inboundLabel || p.populatingFeed || '';
      addPopulationModal.populationType = p.populationType || 'individual';
      addPopulationModal.idFeedIn = p.idFeedIn || '';
      addPopulationModal.feedCategory = p.feedCategory || '';
      addPopulationModal.filterTypeUrl = p.filterTypeUrl || '';
      addPopulationModal.filterUrl = p.filterUrl || '';
      addPopulationModal.filterTypeEmail = p.filterTypeEmail || '';
      addPopulationModal.filterEmail = p.filterEmail || '';
      addPopulationModal.filterTypeListcode = p.filterTypeListcode || '';
      addPopulationModal.filterListcode = p.filterListcode || '';
      addPopulationModal.forceUrl = p.forceUrl ? '1' : '0';
      addPopulationModal.forceUrlList = p.forceUrlList || '';
      addPopulationModal.queueType = p.queueType || 'livedata';
      addPopulationModal.order = p.order ?? 1;
      addPopulationModal.waterfallPriority = p.waterfallPriority ?? 0;
      addPopulationModal.startDate = p.startDate ? p.startDate.split(' ')[0] : '';
      addPopulationModal.enabled = p.enabled === '1' ? '1' : '0';
      addPopulationModal.show = true;
      addPopulationModalError.value = '';
    };

    const closeAddPopulationModal = () => {
      addPopulationModal.show = false;
      addPopulationModal.editId = null;
      addPopulationModalError.value = '';
    };

    const savePopulation = async () => {
      try {
        addPopulationModalError.value = '';
        addPopulationSaving.value = true;
        const payload = {
          enabled: addPopulationModal.enabled,
          order: addPopulationModal.order ?? 1,
          waterfallPriority: addPopulationModal.waterfallPriority,
          queueType: addPopulationModal.queueType,
          startDate: addPopulationModal.startDate || null,
          filterTypeUrl: addPopulationModal.filterTypeUrl || null,
          filterUrl: addPopulationModal.filterTypeUrl ? addPopulationModal.filterUrl : null,
          filterTypeEmail: addPopulationModal.filterTypeEmail || null,
          filterEmail: addPopulationModal.filterTypeEmail ? addPopulationModal.filterEmail : null,
          filterTypeListcode: addPopulationModal.filterTypeListcode || null,
          filterListcode: addPopulationModal.filterTypeListcode ? addPopulationModal.filterListcode : null,
          forceUrl: addPopulationModal.forceUrl === '1' ? 1 : 0,
          forceUrlList: addPopulationModal.forceUrl === '1' ? addPopulationModal.forceUrlList : null,
        };
        if (addPopulationModal.editId) {
          const r = await axios.put(`/api/feed-populations/${addPopulationModal.editId}`, payload);
          if (r.data.status !== 1) throw new Error(r.data.error);
        } else {
          payload.populationType = addPopulationModal.populationType;
          payload.feedCategory = addPopulationModal.populationType === 'category' ? addPopulationModal.feedCategory : null;
          if (addPopulationModal.populationType === 'individual') {
            const feeds = Array.isArray(addPopulationModal.idFeedIn) ? addPopulationModal.idFeedIn : [addPopulationModal.idFeedIn].filter(Boolean);
            if (feeds.length === 0) {
              throw new Error('Please select at least one incoming feed.');
            }
            const errors = [];
            let successCount = 0;
            for (const idFeedIn of feeds) {
              try {
                const r = await axios.post(`/api/outbound-feeds/${populationsModal.idFeedOut}/populations`, { ...payload, idFeedIn });
                if (r.data.status === 1) successCount++;
                else errors.push(r.data.error || 'Unknown error');
              } catch (err) {
                errors.push(err.response?.data?.error || err.message || 'Failed');
              }
            }
            if (successCount === 0) throw new Error(errors[0] || 'Failed to add populations');
            if (errors.length > 0) alert(`Added ${successCount} population(s). Some failed: ${errors.join('; ')}`);
          } else {
            payload.idFeedIn = null;
            const r = await axios.post(`/api/outbound-feeds/${populationsModal.idFeedOut}/populations`, payload);
            if (r.data.status !== 1) throw new Error(r.data.error);
          }
        }
        closeAddPopulationModal();
        await fetchPopulations();
      } catch (e) {
        addPopulationModalError.value = e.response?.data?.error || e.message || 'Failed to save';
      } finally {
        addPopulationSaving.value = false;
      }
    };

    const deletePopulation = async (idAssoc) => {
      if (!confirm('Remove this population?')) return;
      try {
        const r = await axios.delete(`/api/feed-populations/${idAssoc}`);
        if (r.data.status === 1) await fetchPopulations();
      } catch (e) {
        alert(e.response?.data?.error || e.message || 'Failed to delete');
      }
    };

    const openSendTestRecordModal = (feed) => {
      testRecordModal.idFeedOut = feed.idFeedOut;
      testRecordModal.feedLabel = feed.label;
      testRecordModal.email = 'test@example.com';
      testRecordModal.fname = 'Test';
      testRecordModal.lname = 'User';
      testRecordModal.addr = '123 Main St';
      testRecordModal.city = 'New York';
      testRecordModal.state = 'NY';
      testRecordModal.zip = '10001';
      testRecordModal.country = 'US';
      testRecordModal.cellphone = '5551234567';
      testRecordModal.landline = '5559876543';
      testRecordModal.gender = 'M';
      testRecordResult.value = null;
      testRecordModal.show = true;
    };

    const closeTestRecordModal = () => {
      testRecordModal.show = false;
      testRecordModal.idFeedOut = null;
      testRecordResult.value = null;
    };

    const sendTestRecord = async () => {
      if (!testRecordModal.idFeedOut) return;
      testRecordSending.value = true;
      testRecordResult.value = null;
      try {
        const r = await axios.post(`/api/outbound-feeds/${testRecordModal.idFeedOut}/send-test`, {
          email: testRecordModal.email,
          fname: testRecordModal.fname,
          lname: testRecordModal.lname,
          addr: testRecordModal.addr,
          city: testRecordModal.city,
          state: testRecordModal.state,
          zip: testRecordModal.zip,
          country: testRecordModal.country,
          cellphone: testRecordModal.cellphone,
          landline: testRecordModal.landline,
          gender: testRecordModal.gender,
        });
        testRecordResult.value = {
          success: r.data.status === 1 && r.data.success,
          text: r.data.text || r.data.error || r.data.querystring || 'No response',
        };
      } catch (e) {
        testRecordResult.value = {
          success: false,
          text: e.response?.data?.error || e.message || 'Request failed',
        };
      } finally {
        testRecordSending.value = false;
      }
    };

    const openDuplicateFeedModal = async (feed) => {
      try {
        const r = await axios.get(`/api/outbound-feeds/${feed.idFeedOut}`);
        if (r.data.status === 1) {
          const d = r.data.data;
          Object.assign(newFeed, {
            label: (d.label || '') + ' (copy)',
            description: d.description || '',
            idCompany: d.idCompany || '',
            feedType: d.feedType || 'curlPOST',
            postUrl: d.postUrl || '',
            timezone: d.timezone || 'America/New_York',
            feedCategory: d.feedCategory || 'email',
            status: 'active',
            cron: '0',
            cronTiming: d.cronTiming || 1,
            successString: d.successString || '',
            throttle: d.throttle || 100,
            dailyLimit: d.dailyLimit ? String(d.dailyLimit) : '',
            delay: d.delay ? String(d.delay) : '',
            delayDump: d.delayDump ? '1' : '0',
            notifyThresholdCount: d.notifyThresholdCount ? String(d.notifyThresholdCount) : '0',
            notifyThresholdTime: d.notifyThresholdTime || '',
            notifyThresholdDays: d.notifyThresholdDays || [],
            revenuePerLead: d.revenuePerLead ? String(d.revenuePerLead) : '',
            launchDate: d.launchDate || '',
            costPerLeadOverride: d.costPerLeadOverride ? String(d.costPerLeadOverride) : '',
            salesperson: d.salesperson ? String(d.salesperson) : '',
            leadStatus: d.leadStatus || '',
            staticFields: (d.staticFields || []).map((x) => ({ ...x, _id: Math.random().toString(36).slice(2) })),
            varFields: (d.varFields || []).map((x) => ({ ...x, _id: Math.random().toString(36).slice(2) })),
            valueMap: (d.valueMap || []).map((x) => ({ ...x, _id: Math.random().toString(36).slice(2) })),
            urlassignments: (d.urlassignments || []).map((x) => ({ ...x, _id: Math.random().toString(36).slice(2) })),
            xmlDTD: d.xmlDTD || '',
            processingSchedule: d.processingSchedule || defaultProcessingSchedule(),
            prepingEnabled: d.prepingEnabled === true || d.prepingEnabled === 1 || d.prepingEnabled === '1' ? '1' : '0',
            prepingUrl: d.prepingUrl || '',
            prepingHttpMethod: d.prepingHttpMethod === 'GET' ? 'GET' : 'POST',
            prepingAuthType: ['none', 'bearer', 'basic'].includes(d.prepingAuthType) ? d.prepingAuthType : 'none',
            prepingAuthValue: d.prepingAuthValue || '',
          });
          openAddModal();
        }
      } catch (e) {
        alert(e.response?.data?.error || e.message || 'Failed to load feed for duplication');
      }
    };

    const openQueuePreviewModal = async (feed) => {
      queuePreviewModal.idFeedOut = feed.idFeedOut;
      queuePreviewModal.feedLabel = feed.label;
      queuePreviewModal.show = true;
      queuePreviewData.value = [];
      queuePreviewTotal.value = 0;
      queuePreviewLoading.value = true;
      try {
        const r = await axios.get(`/api/outbound-feeds/${feed.idFeedOut}/queue-preview`);
        if (r.data.status === 1 && Array.isArray(r.data.data)) {
          queuePreviewData.value = r.data.data;
          queuePreviewTotal.value = r.data.data.reduce((sum, row) => sum + (parseInt(row.cnt, 10) || 0), 0);
        }
      } catch (e) {
        queuePreviewData.value = [];
      } finally {
        queuePreviewLoading.value = false;
      }
    };

    const closeQueuePreviewModal = () => {
      queuePreviewModal.show = false;
      queuePreviewModal.idFeedOut = null;
      queuePreviewModal.feedLabel = '';
    };

    const openClearQueueModal = (feed) => {
      clearQueueModal.idFeedOut = feed.idFeedOut;
      clearQueueModal.feedLabel = feed.label;
      clearQueueModal.show = true;
      clearQueueMessage.value = null;
    };

    const closeClearQueueModal = () => {
      clearQueueModal.show = false;
      clearQueueModal.idFeedOut = null;
      clearQueueModal.feedLabel = '';
      clearQueueMessage.value = null;
    };

    const submitClearQueue = async () => {
      if (!clearQueueModal.idFeedOut) return;
      clearQueueSending.value = true;
      clearQueueMessage.value = null;
      try {
        const r = await axios.post(`/api/outbound-feeds/${clearQueueModal.idFeedOut}/clear-queue`);
        clearQueueMessage.value = {
          success: r.data.status === 1,
          text: r.data.message || r.data.error || (r.data.status === 1 ? 'Clear queue job submitted.' : 'Failed'),
        };
        if (r.data.status === 1) await fetchFeeds();
      } catch (e) {
        clearQueueMessage.value = { success: false, text: e.response?.data?.error || e.message || 'Failed' };
      } finally {
        clearQueueSending.value = false;
      }
    };

    const openUrlReportModal = async (feed) => {
      urlReportModal.idFeedOut = feed.idFeedOut;
      urlReportModal.feedLabel = feed.label;
      urlReportModal.dateStart = filters.statsStart;
      urlReportModal.dateEnd = filters.statsEnd;
      urlReportModal.urlList = [];
      urlReportModal.breakdown = 'day';
      urlReportModal.group = 'date';
      urlReportModal.sort = 'date';
      urlReportModal.show = true;
      urlReportResults.value = [];
      try {
        const r = await axios.get(`/api/outbound-feeds/${feed.idFeedOut}/url-list`);
        urlReportUrlList.value = r.data.status === 1 ? r.data.data : [];
      } catch (e) {
        urlReportUrlList.value = [];
      }
    };

    const closeUrlReportModal = () => {
      urlReportModal.show = false;
      urlReportModal.idFeedOut = null;
      urlReportModal.feedLabel = '';
    };

    const runUrlReport = async () => {
      if (!urlReportModal.idFeedOut) return;
      urlReportLoading.value = true;
      urlReportResults.value = [];
      try {
        const r = await axios.get(`/api/outbound-feeds/${urlReportModal.idFeedOut}/url-report`, {
          params: {
            dateStart: urlReportModal.dateStart || undefined,
            dateEnd: urlReportModal.dateEnd || undefined,
            urlList: urlReportModal.urlList?.length ? urlReportModal.urlList : undefined,
            breakdown: urlReportModal.breakdown,
            group: urlReportModal.group,
            sort: urlReportModal.sort,
          },
        });
        urlReportResults.value = r.data.status === 1 ? r.data.data : [];
      } catch (e) {
        urlReportResults.value = [];
      } finally {
        urlReportLoading.value = false;
      }
    };

    const openImportDataModal = (feed) => {
      importDataModal.idFeedOut = feed.idFeedOut;
      importDataModal.feedLabel = feed.label;
      importDataModal.dateStart = filters.statsStart;
      importDataModal.dateEnd = filters.statsEnd;
      importDataModal.message = '';
      importDataModal.success = false;
      importDataModal.show = true;
    };

    const closeImportDataModal = () => {
      importDataModal.show = false;
      importDataModal.idFeedOut = null;
      importDataModal.feedLabel = '';
    };

    const submitImportData = async () => {
      if (!importDataModal.idFeedOut) return;
      importDataSending.value = true;
      importDataModal.message = '';
      try {
        const r = await axios.post(`/api/outbound-feeds/${importDataModal.idFeedOut}/import`, {
          dateStart: importDataModal.dateStart,
          dateEnd: importDataModal.dateEnd,
        });
        importDataModal.message = r.data.message || r.data.error || (r.data.status === 1 ? 'Import job submitted.' : 'Failed');
        importDataModal.success = r.data.status === 1;
        if (r.data.status === 1) await fetchFeeds();
      } catch (e) {
        importDataModal.message = e.response?.data?.error || e.message || 'Failed';
        importDataModal.success = false;
      } finally {
        importDataSending.value = false;
      }
    };

    const openUploadDataModal = (feed) => {
      uploadDataModal.idFeedOut = feed.idFeedOut;
      uploadDataModal.feedLabel = feed.label;
      uploadDataModal.file = null;
      uploadDataModal.message = '';
      uploadDataModal.success = false;
      uploadDataModal.show = true;
      if (uploadFileInput.value) uploadFileInput.value.value = '';
    };

    const closeUploadDataModal = () => {
      uploadDataModal.show = false;
      uploadDataModal.idFeedOut = null;
      uploadDataModal.feedLabel = '';
      uploadDataModal.file = null;
    };

    const onUploadFileSelect = (e) => {
      uploadDataModal.file = e.target.files?.[0] || null;
    };

    const submitUploadData = async () => {
      if (!uploadDataModal.idFeedOut || !uploadDataModal.file) return;
      uploadDataSending.value = true;
      uploadDataModal.message = '';
      try {
        const formData = new FormData();
        formData.append('file', uploadDataModal.file);
        const r = await axios.post(`/api/outbound-feeds/${uploadDataModal.idFeedOut}/upload`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        uploadDataModal.message = r.data.message || r.data.error || (r.data.status === 1 ? 'Upload job submitted.' : 'Failed');
        uploadDataModal.success = r.data.status === 1;
        if (r.data.status === 1) await fetchFeeds();
      } catch (e) {
        uploadDataModal.message = e.response?.data?.error || e.message || 'Failed';
        uploadDataModal.success = false;
      } finally {
        uploadDataSending.value = false;
      }
    };

    const openExportDataModal = async (feed) => {
      exportDataModal.idFeedOut = feed.idFeedOut;
      exportDataModal.feedLabel = feed.label;
      exportDataModal.dateStart = filters.statsStart;
      exportDataModal.dateEnd = filters.statsEnd;
      exportDataModal.limit = '';
      exportDataModal.includeRejects = false;
      exportDataModal.message = '';
      exportDataModal.success = false;
      exportDataModal.show = true;
      try {
        const r = await axios.get(`/api/outbound-feeds/${feed.idFeedOut}/export-columns`);
        if (r.data.status === 1 && Array.isArray(r.data.data)) {
          exportColumns.value = (r.data.data || []).map((f) => ({ fieldName: f.fieldName, checked: true }));
        } else {
          exportColumns.value = [];
        }
      } catch (e) {
        exportColumns.value = [];
      }
    };

    const closeExportDataModal = () => {
      exportDataModal.show = false;
      exportDataModal.idFeedOut = null;
      exportDataModal.feedLabel = '';
    };

    const submitExportData = async () => {
      if (!exportDataModal.idFeedOut) return;
      const cols = (exportColumns.value || []).filter((c) => c.checked).map((c) => c.fieldName);
      if (!cols.length) {
        exportDataModal.message = 'Select at least one column.';
        return;
      }
      exportDataSending.value = true;
      exportDataModal.message = '';
      try {
        const r = await axios.post(`/api/outbound-feeds/${exportDataModal.idFeedOut}/export`, {
          columns: cols,
          dateStart: exportDataModal.dateStart,
          dateEnd: exportDataModal.dateEnd,
          limit: exportDataModal.limit || undefined,
          includeRejects: exportDataModal.includeRejects,
        }, { responseType: 'blob' });
        const contentType = r.headers['content-type'] || '';
        if (contentType.includes('application/json')) {
          const text = await r.data.text();
          const err = JSON.parse(text);
          exportDataModal.message = err.error || 'Export failed';
          exportDataModal.success = false;
        } else {
          const blob = new Blob([r.data], { type: 'text/csv' });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `outbound_${exportDataModal.idFeedOut}_${Date.now()}.csv`;
          a.click();
          URL.revokeObjectURL(url);
          exportDataModal.message = 'Export downloaded.';
          exportDataModal.success = true;
        }
      } catch (e) {
        let errMsg = e.message || 'Export failed';
        if (e.response?.data) {
          if (e.response.data instanceof Blob) {
            try {
              const text = await e.response.data.text();
              const j = JSON.parse(text);
              errMsg = j.error || errMsg;
            } catch (_) {}
          } else {
            errMsg = e.response.data.error || errMsg;
          }
        }
        exportDataModal.message = errMsg;
        exportDataModal.success = false;
      } finally {
        exportDataSending.value = false;
      }
    };

    const openRetryRejectionsModal = (feed) => {
      retryRejectionsModal.idFeedOut = feed.idFeedOut;
      retryRejectionsModal.feedLabel = feed.label;
      retryRejectionsModal.dateStart = filters.statsStart;
      retryRejectionsModal.dateEnd = filters.statsEnd;
      retryRejectionsModal.message = '';
      retryRejectionsModal.success = false;
      retryRejectionsModal.show = true;
    };

    const closeRetryRejectionsModal = () => {
      retryRejectionsModal.show = false;
      retryRejectionsModal.idFeedOut = null;
      retryRejectionsModal.feedLabel = '';
    };

    const submitRetryRejections = async () => {
      if (!retryRejectionsModal.idFeedOut) return;
      retryRejectionsSending.value = true;
      retryRejectionsModal.message = '';
      try {
        const r = await axios.post(`/api/outbound-feeds/${retryRejectionsModal.idFeedOut}/retry-rejections`, {
          dateStart: retryRejectionsModal.dateStart,
          dateEnd: retryRejectionsModal.dateEnd,
        });
        retryRejectionsModal.message = r.data.message || r.data.error || (r.data.status === 1 ? 'Retry rejections job submitted.' : 'Failed');
        retryRejectionsModal.success = r.data.status === 1;
        if (r.data.status === 1) await fetchFeeds();
      } catch (e) {
        retryRejectionsModal.message = e.response?.data?.error || e.message || 'Failed';
        retryRejectionsModal.success = false;
      } finally {
        retryRejectionsSending.value = false;
      }
    };

    const openEditModal = async (feed) => {
      editError.value = '';
      try {
        // Fetch full feed details
        const response = await axios.get(`/api/outbound-feeds/${feed.idFeedOut}`);
        if (response.data.status === 1) {
          const feedData = response.data.data;
          
          // Populate editingFeed with fetched data
          Object.assign(editingFeed, {
            idFeedOut: feedData.idFeedOut,
            label: feedData.label || '',
            description: feedData.description || '',
            idCompany: feedData.idCompany || '',
            feedType: feedData.feedType || 'curlPOST',
            postUrl: feedData.postUrl || '',
            timezone: feedData.timezone || 'America/New_York',
            feedCategory: feedData.feedCategory || 'email',
            responseType: feedData.responseType || 'realtime',
            webhookSecret: feedData.webhookSecret || '',
            status: feedData.status || 'active',
            cron: feedData.cron === '1' || feedData.cron === 1 || feedData.cron === true ? '1' : '0',
            cronTiming: feedData.cronTiming || 1,
            successString: feedData.successString || '',
            throttle: feedData.throttle || 100,
            dailyLimit: feedData.dailyLimit ? String(feedData.dailyLimit) : '',
            delay: feedData.delay ? String(feedData.delay) : '',
            delayDump: feedData.delayDump ? '1' : '0',
            notifyThresholdCount: feedData.notifyThresholdCount ? String(feedData.notifyThresholdCount) : '0',
            notifyThresholdTime: feedData.notifyThresholdTime || '',
            notifyThresholdDays: feedData.notifyThresholdDays || [],
            revenuePerLead: feedData.revenuePerLead ? String(feedData.revenuePerLead) : '',
            launchDate: feedData.launchDate || '',
            urlassignments: feedData.urlassignments || [],
            xmlDTD: feedData.xmlDTD || '',
            processingSchedule: feedData.processingSchedule || defaultProcessingSchedule(),
            costPerLeadOverride: feedData.costPerLeadOverride ? String(feedData.costPerLeadOverride) : '',
            salesperson: feedData.salesperson ? String(feedData.salesperson) : '',
            leadStatus: feedData.leadStatus || '',
            staticFields: feedData.staticFields || [],
            varFields: feedData.varFields || [],
            valueMap: feedData.valueMap || [],
            prepingEnabled: feedData.prepingEnabled === true || feedData.prepingEnabled === 1 || feedData.prepingEnabled === '1' ? '1' : '0',
            prepingUrl: feedData.prepingUrl || '',
            prepingHttpMethod: feedData.prepingHttpMethod === 'GET' ? 'GET' : 'POST',
            prepingAuthType: ['none', 'bearer', 'basic'].includes(feedData.prepingAuthType) ? feedData.prepingAuthType : 'none',
            prepingAuthValue: feedData.prepingAuthValue || '',
          });
          
          // Wait for Vue to update the reactive object - wait multiple ticks to ensure form renders
          await nextTick();
          await nextTick();
          
          // Now show the modal
          if (window.$ && window.$('#editFeedModal')) {
            window.$('#editFeedModal').modal('show');
          }
        } else {
          editError.value = 'Failed to load feed data.';
        }
      } catch (error) {
        editError.value = 'Failed to load feed data: ' + (error.response?.data?.error || error.message);
        // Still show modal to display error
        await nextTick();
        if (window.$ && window.$('#editFeedModal')) {
          window.$('#editFeedModal').modal('show');
        }
      }
    };

    const closeEditModal = () => {
      editError.value = '';
      // Reset editingFeed
      Object.assign(editingFeed, {
        idFeedOut: null,
        label: '',
        description: '',
        idCompany: '',
        feedType: 'curlPOST',
        postUrl: '',
        timezone: 'America/New_York',
        feedCategory: 'email',
        responseType: 'realtime',
        webhookSecret: '',
        status: 'active',
        cron: '0',
        cronTiming: 1,
        successString: '',
        throttle: 100,
        dailyLimit: '',
        delay: '',
        delayDump: '0',
        notifyThresholdCount: '0',
        notifyThresholdTime: '',
        notifyThresholdDays: [],
        revenuePerLead: '',
        launchDate: '',
        costPerLeadOverride: '',
        salesperson: '',
        leadStatus: '',
        staticFields: [],
        varFields: [],
        valueMap: [],
        prepingEnabled: '0',
        prepingUrl: '',
        prepingHttpMethod: 'POST',
        prepingAuthType: 'none',
        prepingAuthValue: '',
      });
      if (window.$ && window.$('#editFeedModal')) {
        window.$('#editFeedModal').modal('hide');
      }
    };

    const fetchCompanies = async () => {
      try {
        const response = await axios.get('/api/companies/dropdown', { params: { status: 'active' } });
        if (response.data.status === 1) {
          companies.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching companies:', error);
      }
    };

    const fetchAvailableFields = async () => {
      try {
        const response = await axios.get('/api/outbound-feeds/available-fields');
        if (response.data.status === 1) {
          availableFields.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching available fields:', error);
      }
    };

    const fetchFeedCategories = async () => {
      try {
        const response = await axios.get('/api/outbound-feeds/categories');
        if (response.data.status === 1) {
          feedCategories.value = response.data.data || {};
        }
      } catch (error) {
        console.error('Error fetching feed categories:', error);
      }
    };

    const fetchFeedTypes = async () => {
      try {
        const response = await axios.get('/api/outbound-feeds/feed-types');
        if (response.data.status === 1) {
          feedTypes.value = response.data.data || {};
        }
      } catch (error) {
        console.error('Error fetching feed types:', error);
      }
    };

    const fetchTimezones = async () => {
      try {
        const response = await axios.get('/api/outbound-feeds/timezones');
        if (response.data.status === 1) {
          timezones.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching timezones:', error);
      }
    };

    const fetchStaffUsers = async () => {
      try {
        const response = await axios.get('/api/companies/staff-users');
        if (response.data.status === 1) {
          staffUsers.value = response.data.data || [];
        }
      } catch (error) {
        console.error('Error fetching staff users:', error);
      }
    };

    const updateNewFeed = (updatedFeed) => {
      Object.assign(newFeed, updatedFeed);
    };

    const updateEditingFeed = (updatedFeed) => {
      Object.assign(editingFeed, updatedFeed);
    };

    const handleAddFeed = async () => {
      if (!newFeed.label.trim()) {
        addError.value = 'Feed label cannot be empty.';
        return;
      }

      if (!newFeed.idCompany) {
        addError.value = 'Company cannot be empty.';
        return;
      }

      if (!newFeed.postUrl.trim()) {
        addError.value = 'Post URL cannot be empty.';
        return;
      }

      adding.value = true;
      addError.value = '';

      try {
        const payload = {
          label: newFeed.label.trim(),
          description: newFeed.description.trim() || null,
          idCompany: parseInt(newFeed.idCompany),
          feedType: newFeed.feedType,
          postUrl: newFeed.postUrl.trim(),
          timezone: newFeed.timezone,
          feedCategory: newFeed.feedCategory,
          responseType: newFeed.responseType || 'realtime',
          webhookSecret: newFeed.webhookSecret?.trim() || null,
          status: newFeed.status,
          cron: newFeed.cron,
          cronTiming: parseInt(newFeed.cronTiming),
          successString: newFeed.successString.trim() || null,
          throttle: parseInt(newFeed.throttle),
          dailyLimit: newFeed.dailyLimit ? parseInt(newFeed.dailyLimit) : null,
          delay: newFeed.delay ? parseInt(newFeed.delay) : null,
          delayDump: newFeed.delayDump === '1' ? '1' : '0',
          notifyThresholdCount: newFeed.notifyThresholdCount ? parseInt(newFeed.notifyThresholdCount) : 0,
          notifyThresholdTime: newFeed.notifyThresholdTime.trim() || null,
          notifyThresholdDays: newFeed.notifyThresholdDays,
          revenuePerLead: newFeed.revenuePerLead ? parseFloat(newFeed.revenuePerLead) : null,
          launchDate: newFeed.launchDate || null,
          costPerLeadOverride: newFeed.costPerLeadOverride ? parseFloat(newFeed.costPerLeadOverride) : null,
          salesperson: newFeed.salesperson ? parseInt(newFeed.salesperson) : null,
          leadStatus: newFeed.leadStatus.trim() || null,
          staticFields: newFeed.staticFields || [],
          varFields: newFeed.varFields || [],
          valueMap: newFeed.valueMap || [],
          urlassignments: newFeed.urlassignments || [],
          xmlDTD: newFeed.xmlDTD?.trim() || null,
          processingSchedule: newFeed.processingSchedule || null,
          prepingEnabled: newFeed.prepingEnabled === '1' || newFeed.prepingEnabled === true,
          prepingUrl: newFeed.prepingUrl?.trim() || null,
          prepingHttpMethod: newFeed.prepingHttpMethod || 'POST',
          prepingAuthType: newFeed.prepingAuthType || 'none',
          prepingAuthValue: newFeed.prepingAuthValue?.trim() || null,
        };

        const response = await axios.post('/api/outbound-feeds', payload);

        if (response.data.status === 1) {
          closeAddModal();
          // Reset form
          Object.assign(newFeed, {
            label: '',
            description: '',
            idCompany: '',
            feedType: 'curlPOST',
            postUrl: '',
            timezone: 'America/New_York',
            feedCategory: 'email',
            responseType: 'realtime',
            webhookSecret: '',
            status: 'active',
            cron: '0',
            cronTiming: 1,
            successString: '',
            throttle: 100,
            dailyLimit: '',
            delay: '',
            delayDump: '0',
            notifyThresholdCount: '0',
            notifyThresholdTime: '',
            notifyThresholdDays: [],
            revenuePerLead: '',
            launchDate: '',
            costPerLeadOverride: '',
            salesperson: '',
            leadStatus: '',
            staticFields: [],
            varFields: [],
            valueMap: [],
            urlassignments: [],
            xmlDTD: '',
            processingSchedule: defaultProcessingSchedule(),
            prepingEnabled: '0',
            prepingUrl: '',
            prepingHttpMethod: 'POST',
            prepingAuthType: 'none',
            prepingAuthValue: '',
          });
          await fetchFeeds();
        } else {
          addError.value = response.data.error || 'Failed to add feed.';
        }
      } catch (error) {
        addError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to add feed.';
      } finally {
        adding.value = false;
      }
    };

    const handleUpdateFeed = async () => {
      if (!editingFeed.label.trim()) {
        editError.value = 'Feed label cannot be empty.';
        return;
      }

      if (!editingFeed.idCompany) {
        editError.value = 'Company cannot be empty.';
        return;
      }

      if (!editingFeed.postUrl.trim()) {
        editError.value = 'Post URL cannot be empty.';
        return;
      }

      updating.value = true;
      editError.value = '';

      try {
        const payload = {
          label: editingFeed.label.trim(),
          description: editingFeed.description.trim() || null,
          idCompany: parseInt(editingFeed.idCompany),
          feedType: editingFeed.feedType,
          postUrl: editingFeed.postUrl.trim(),
          timezone: editingFeed.timezone,
          feedCategory: editingFeed.feedCategory,
          responseType: editingFeed.responseType || 'realtime',
          webhookSecret: editingFeed.webhookSecret?.trim() || null,
          status: editingFeed.status,
          cron: editingFeed.cron,
          cronTiming: parseInt(editingFeed.cronTiming),
          successString: editingFeed.successString.trim() || null,
          throttle: parseInt(editingFeed.throttle),
          dailyLimit: editingFeed.dailyLimit ? parseInt(editingFeed.dailyLimit) : null,
          delay: editingFeed.delay ? parseInt(editingFeed.delay) : null,
          delayDump: editingFeed.delayDump === '1' ? '1' : '0',
          notifyThresholdCount: editingFeed.notifyThresholdCount ? parseInt(editingFeed.notifyThresholdCount) : 0,
          notifyThresholdTime: editingFeed.notifyThresholdTime.trim() || null,
          notifyThresholdDays: editingFeed.notifyThresholdDays,
          revenuePerLead: editingFeed.revenuePerLead ? parseFloat(editingFeed.revenuePerLead) : null,
          launchDate: editingFeed.launchDate || null,
          costPerLeadOverride: editingFeed.costPerLeadOverride ? parseFloat(editingFeed.costPerLeadOverride) : null,
          salesperson: editingFeed.salesperson ? parseInt(editingFeed.salesperson) : null,
          leadStatus: editingFeed.leadStatus.trim() || null,
          staticFields: editingFeed.staticFields || [],
          varFields: editingFeed.varFields || [],
          valueMap: editingFeed.valueMap || [],
          urlassignments: editingFeed.urlassignments || [],
          xmlDTD: editingFeed.xmlDTD?.trim() || null,
          processingSchedule: editingFeed.processingSchedule || null,
          prepingEnabled: editingFeed.prepingEnabled === '1' || editingFeed.prepingEnabled === true,
          prepingUrl: editingFeed.prepingUrl?.trim() || null,
          prepingHttpMethod: editingFeed.prepingHttpMethod || 'POST',
          prepingAuthType: editingFeed.prepingAuthType || 'none',
          prepingAuthValue: editingFeed.prepingAuthValue?.trim() || null,
        };

        const response = await axios.put(`/api/outbound-feeds/${editingFeed.idFeedOut}`, payload);

        if (response.data.status === 1) {
          closeEditModal();
          await fetchFeeds();
        } else {
          editError.value = response.data.error || 'Failed to update feed.';
        }
      } catch (error) {
        editError.value =
          error.response?.data?.error ||
          error.message ||
          'Failed to update feed.';
      } finally {
        updating.value = false;
      }
    };

    onMounted(async () => {
      document.addEventListener('click', () => {
        openDropdownFeedId.value = null;
      });
      await Promise.all([
        fetchCompanies(),
        fetchAvailableFields(),
        fetchFeedCategories(),
        fetchFeedTypes(),
        fetchTimezones(),
        fetchStaffUsers(),
        fetchFeeds(),
      ]);
    });

    return {
      companyGroups,
      companies,
      availableFields,
      feedCategories,
      feedTypes,
      timezones,
      staffUsers,
      newFeed,
      loading,
      adding,
      updating,
      addError,
      editError,
      filters,
      expandedCompanies,
      openDropdownFeedId,
      toggleFeedDropdown,
      formatNumber,
      fetchFeeds,
      toggleCompanyFeeds,
      toggleStatus,
      outgoingRecordSearchLink,
      openAddModal,
      closeAddModal,
      openEditModal,
      openShowPopulationsModal,
      openSendTestRecordModal,
      closeEditModal,
      updateNewFeed,
      updateEditingFeed,
      handleAddFeed,
      handleUpdateFeed,
      editingFeed,
      populationsModal,
      populations,
      populationsLoading,
      inboundFeedsForPopulation,
      availableInboundFeedsForPopulation,
      feedCategoriesForPopulation,
      addPopulationModal,
      addPopulationModalError,
      addPopulationSaving,
      openAddPopulationModal,
      openEditPopulationModal,
      closeAddPopulationModal,
      closePopulationsModal,
      savePopulation,
      deletePopulation,
      togglePopulation,
      testRecordModal,
      testRecordResult,
      testRecordSending,
      closeTestRecordModal,
      sendTestRecord,
      openDuplicateFeedModal,
      queuePreviewModal,
      queuePreviewData,
      queuePreviewTotal,
      queuePreviewLoading,
      openQueuePreviewModal,
      closeQueuePreviewModal,
      clearQueueModal,
      clearQueueMessage,
      clearQueueSending,
      openClearQueueModal,
      closeClearQueueModal,
      submitClearQueue,
      urlReportModal,
      urlReportUrlList,
      urlReportResults,
      urlReportLoading,
      openUrlReportModal,
      closeUrlReportModal,
      runUrlReport,
      importDataModal,
      importDataSending,
      openImportDataModal,
      closeImportDataModal,
      submitImportData,
      uploadDataModal,
      uploadDataSending,
      uploadFileInput,
      openUploadDataModal,
      closeUploadDataModal,
      onUploadFileSelect,
      submitUploadData,
      exportDataModal,
      exportColumns,
      exportDataSending,
      openExportDataModal,
      closeExportDataModal,
      submitExportData,
      retryRejectionsModal,
      retryRejectionsSending,
      openRetryRejectionsModal,
      closeRetryRejectionsModal,
      submitRetryRejections,
    };
  },
};
</script>

<style scoped>
/* Outgoing feed modals - Teleport to body, visible when v-show is true */
.outgoing-feed-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.5);
  overflow-x: hidden;
  overflow-y: auto;
  padding: 20px;
}
.outgoing-feed-modal .modal-dialog {
  margin: auto;
  background: #fff;
  border-radius: 4px;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
  max-width: 90%;
}

/* Add Population Modal - legacy-style layout */
.add-population-modal .modal-dialog {
  width: 800px;
  max-width: 95%;
}
.add-population-modal .modal-content {
  border: 1px solid #ddd;
  border-radius: 4px;
}
.add-population-modal .modal-header {
  background: #072f5f;
  color: #fff;
  border-bottom: none;
  padding: 12px 15px;
  border-radius: 4px 4px 0 0;
}
.add-population-modal .modal-header .modal-title {
  font-size: 16px;
  font-weight: 600;
}
.add-population-modal .modal-header .close {
  color: #fff;
  opacity: 0.9;
  text-shadow: none;
  margin-top: -2px;
}
.add-population-modal .modal-header .close:hover {
  opacity: 1;
}
.add-population-modal .modal-body {
  padding: 20px;
  max-height: 70vh;
  overflow-y: auto;
}
.add-population-modal .modal-footer {
  padding: 12px 20px;
  border-top: 1px solid #eee;
  background: #f9f9f9;
}
.add-pop-row {
  display: flex;
  align-items: flex-start;
  margin-bottom: 0;
  padding-bottom: 0;
}
.add-pop-row + .add-pop-row {
  margin-top: 18px;
  padding-top: 18px;
  border-top: 1px solid #eee;
}
.add-pop-heading {
  flex: 0 0 180px;
  font-weight: 600;
  color: #333;
  font-size: 14px;
  line-height: 1.4;
  padding-right: 20px;
  padding-top: 2px;
}
.add-pop-content {
  flex: 1;
  min-width: 0;
}
.add-pop-desc {
  color: #666;
  font-size: 13px;
  line-height: 1.5;
  margin: 0 0 10px 0;
}
.add-pop-radios {
  margin-bottom: 10px;
}
.add-pop-radio {
  display: inline-block;
  margin-right: 20px;
  margin-bottom: 0;
  font-weight: normal;
  cursor: pointer;
  font-size: 14px;
}
.add-pop-radio input {
  margin-right: 6px;
  vertical-align: middle;
}
.add-pop-select,
.add-pop-input {
  max-width: 450px;
  margin-top: 6px;
}
.add-pop-input-sm {
  max-width: 180px;
}
.add-pop-queue-options {
  margin-top: 6px;
}
.add-pop-queue-opt {
  display: block;
  margin-bottom: 8px;
  font-weight: normal;
  cursor: pointer;
  font-size: 14px;
  line-height: 1.4;
}
.add-pop-queue-opt input {
  margin-right: 8px;
  vertical-align: middle;
}
.add-pop-checkbox {
  cursor: pointer;
  font-weight: normal;
}
.add-pop-checkbox input {
  margin-right: 6px;
}
.add-pop-readonly {
  margin: 8px 0 0 0;
  padding: 6px 10px;
  background: #f5f5f5;
  border-radius: 4px;
  font-size: 14px;
}

.pt-2 {
  padding-top: 20px;
}
.table th {
  background-color: #072f5f !important;
  color: #fff !important;
  text-align: center;
  vertical-align: middle;
}

.bgGray {
  background-color: #072f5f !important;
  color: #fff !important;
}

.custom-master {
  font-weight: bold;
}

.feed-toggle {
  background-color: #f9f9f9;
}

.bg-gray {
  background-color: #f9f9f9;
}

.radio-inline {
  display: inline-block;
  margin-right: 15px;
  margin-bottom: 0;
  font-weight: normal;
  cursor: pointer;
}
.radio-inline input {
  margin-right: 5px;
}

.status-active {
  color: #429038;
}

.status-hidden {
  color: #999;
}

.status-retired {
  color: #d9534f;
}

.outgoing-col-large {
  width: 30%;
}

.outgoing-col-small {
  width: 10%;
}

.text-right {
  text-align: right;
}

/* Toggle Switch Styles */
.switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 24px;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: 0.4s;
  border-radius: 24px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.4s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: #429038;
}

input:checked + .slider:before {
  transform: translateX(26px);
}

.record-search-link-cell a {
  color: inherit;
  text-decoration: none;
}
.record-search-link-cell a:hover {
  text-decoration: underline;
}
</style>

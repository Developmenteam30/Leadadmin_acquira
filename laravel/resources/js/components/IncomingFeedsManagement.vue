<template>
  <div>
    <Navigation />
    <div class="container-fluid">
      <h2>Incoming Feeds</h2>
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
        <router-link to="/incoming-feeds/ping" class="btn btn-primary" style="margin-left: 10px;">
          Ping Requests
        </router-link>
      </p>

      <div v-if="loading" class="text-center">
        <p>Loading...</p>
      </div>

      <div v-else-if="companyGroups.length === 0">
        <p>No incoming feeds found.</p>
      </div>

      <div v-else>
        <h4>Incoming Phone Feeds</h4>
        <table class="table table-bordered table-condensed table-striped">
          <thead>
            <tr class="bgGray">
              <th class="incoming-col-large" colspan="2">Company</th>
              <th class="incoming-col-small text-right">Accepted</th>
              <th class="incoming-col-small text-right">Rejected</th>
              <th class="incoming-col-small">Actions</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(company, index) in companyGroups" :key="company.idCompany">
              <tr class="custom-master">
                <td colspan="2">
                  {{ company.name }} ({{ company.feeds.length }})
                </td>
                <td class="text-right record-search-link-cell">
                  <router-link
                    :to="recordSearchLink(company.idCompany, null, 'accepted')"
                  >
                    {{ formatNumber(company.totalAccepted) }}
                  </router-link>
                </td>
                <td class="text-right record-search-link-cell">
                  <router-link
                    :to="recordSearchLink(company.idCompany, null, 'rejected')"
                  >
                    {{ formatNumber(company.totalRejected) }}
                  </router-link>
                </td>
                <td class="text-center">
                  <button
                    class="btn btn-primary btn-xs"
                    type="button"
                    @click="toggleCompanyFeeds(company.idCompany)"
                  >
                    {{ expandedCompanies[company.idCompany] ? 'Hide Feeds' : 'Show Feeds' }}
                  </button>
                </td>
              </tr>
              <template v-if="expandedCompanies[company.idCompany]">
                <tr
                  v-for="feed in company.feeds"
                  :key="feed.idFeedIn"
                  class="bg-gray feed-toggle"
                >
                  <td :class="'status-' + feed.status">
                    {{ feed.idFeedIn }}: {{ feed.label }}
                    <span v-if="feed.description">({{ feed.description }})</span>
                  </td>
                  <td>
                    <label class="switch">
                      <input
                        type="checkbox"
                        :checked="!feed.paused"
                        @change="togglePause(feed.idFeedIn, !feed.paused)"
                      />
                      <span class="slider"></span>
                    </label>
                    <span style="margin-left: 10px;">{{ feed.paused ? 'Paused' : 'Enabled' }}</span>
                  </td>
                  <td class="text-right record-search-link-cell">
                    <router-link
                      :to="recordSearchLink(null, feed.idFeedIn, 'accepted')"
                    >
                      {{ formatNumber(feed.accepted) }}
                    </router-link>
                  </td>
                  <td class="text-right record-search-link-cell">
                    <router-link
                      :to="recordSearchLink(null, feed.idFeedIn, 'rejected')"
                    >
                      {{ formatNumber(feed.rejected) }}
                    </router-link>
                  </td>
                  <td class="text-center">
                    <div class="btn-group" :class="{ open: openDropdownFeedId === feed.idFeedIn }">
                      <button
                        type="button"
                        class="btn btn-primary btn-xs"
                        @click="openEditModal(feed)"
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        class="btn btn-primary btn-xs dropdown-toggle"
                        @click.stop="toggleFeedDropdown(feed.idFeedIn)"
                        aria-haspopup="true"
                        :aria-expanded="openDropdownFeedId === feed.idFeedIn"
                      >
                        <span class="caret"></span>
                        <span class="sr-only">Toggle Dropdown</span>
                      </button>
                      <ul
                        v-show="openDropdownFeedId === feed.idFeedIn"
                        class="dropdown-menu dropdown-menu-right"
                        @click="openDropdownFeedId = null"
                      >
                        <li><a href="#" @click.prevent="openApiSpec(feed)">API Spec</a></li>
                        <li><a href="#" @click.prevent="openOutgoingFeedsModal(feed)">Connect Outgoing Feeds</a></li>
                        <li><a href="#" @click.prevent="openImportDataModal(feed)">Import Data</a></li>
                        <li><a href="#" @click.prevent="openExportDataModal(feed)">Export Data</a></li>
                        <li><a href="#" @click.prevent="openUrlReportModal(feed)">URL Report</a></li>
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
              <InboundFeedForm
                ref="addFeedForm"
                :feed="newFeed"
                :companies="companies"
                :availableFields="availableFields"
                :feedCategories="feedCategories"
                :timezones="timezones"
                :staffUsers="staffUsers"
                :usStates="usStates"
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
                {{ adding ? 'Adding...' : 'Add Feed' }}
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
              <div v-if="!editingFeed.idFeedIn && !editError" class="text-center" style="padding: 20px;">
                <p>Loading feed data...</p>
              </div>
              <template v-else-if="editingFeed.idFeedIn">
                <InboundFeedForm
                  :key="editingFeed.idFeedIn"
                  ref="editFeedForm"
                  :feed="editingFeed"
                  :companies="companies"
                  :availableFields="availableFields"
                  :feedCategories="feedCategories"
                  :timezones="timezones"
                  :staffUsers="staffUsers"
                  :usStates="usStates"
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

      <!-- Incoming Feed Modals (Teleport to body for proper stacking) -->
      <Teleport to="body">
        <!-- API Spec Modal -->
        <div v-show="apiSpecModal.show" class="incoming-feed-modal" tabindex="-1" @click.self="closeApiSpecModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">API Spec – {{ apiSpecModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeApiSpecModal">&times;</button>
              </div>
              <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div v-if="apiSpecLoading">Loading...</div>
                <template v-else-if="apiSpecModal.data">
                  <p><strong>Company:</strong> {{ apiSpecModal.data.company }}</p>
                  <p><strong>Feed:</strong> {{ apiSpecModal.data.label }} (#{{ apiSpecModal.data.feedId }})</p>
                  <p><strong>Password:</strong> {{ apiSpecModal.data.password }}</p>
                  <p v-if="apiSpecModal.data.feedUrl" class="mb-2">
                    <strong>API URL (lead submission):</strong>
                    <code class="d-block mt-1 p-2 bg-light rounded" style="word-break: break-all;">{{ apiSpecModal.data.feedUrl }}</code>
                    <button type="button" class="btn btn-xs btn-default mt-1" @click="copyToClipboard(apiSpecModal.data.feedUrl)" title="Copy">Copy</button>
                  </p>
                  <p v-if="apiSpecModal.data.apiSpecUrl" class="mb-2">
                    <strong>API Spec URL (shareable):</strong>
                    <code class="d-block mt-1 p-2 bg-light rounded" style="word-break: break-all;">{{ apiSpecModal.data.apiSpecUrl }}</code>
                    <button type="button" class="btn btn-xs btn-default mt-1" @click="copyToClipboard(apiSpecModal.data.apiSpecUrl)" title="Copy">Copy</button>
                  </p>
                  <p><strong>Required:</strong> {{ (apiSpecModal.data.required || []).join(', ') }}</p>
                  <p><strong>Allowed:</strong> {{ (apiSpecModal.data.allowedFields || []).join(', ') }}</p>
                  <table class="table table-bordered table-condensed">
                    <thead><tr><th>Field</th><th>Description</th></tr></thead>
                    <tbody>
                      <tr v-for="f in (apiSpecModal.data.fields || [])" :key="f.fieldName">
                        <td>{{ f.fieldName }}</td>
                        <td>{{ f.fieldDescription }}</td>
                      </tr>
                    </tbody>
                  </table>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Import Data Modal -->
        <div v-show="importDataModal.show" class="incoming-feed-modal" tabindex="-1" @click.self="closeImportDataModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Import Data – {{ importDataModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeImportDataModal">&times;</button>
              </div>
              <div class="modal-body">
                <p>Upload CSV or Excel file. Map each required field to a column (A=1st column, B=2nd, etc.).</p>
                <div class="form-group">
                  <label>File</label>
                  <input ref="importFileInput" type="file" accept=".csv,.xlsx,.xls" class="form-control" @change="onImportFileSelect" />
                </div>
                <div v-if="importDataModal.allowedFields.length" class="form-group">
                  <label>Field mapping</label>
                  <p class="text-muted">Select which column (A, B, C...) contains each field. Required fields are marked with *.</p>
                  <div v-for="field in importDataModal.allowedFields" :key="field" class="mb-2">
                    {{ field }}{{ importDataModal.requiredFields.includes(field) ? ' *' : '' }}
                    <select v-model="importDataModal.fieldMapping[field]" class="form-control" style="display: inline-block; width: auto; margin-left: 8px;">
                      <option value="">--</option>
                      <option v-for="(col, idx) in importColumnOptions" :key="idx" :value="idx">{{ col }}</option>
                    </select>
                    <span v-if="field === 'stamp'" class="text-muted">(date+time or date only)</span>
                  </div>
                </div>
                <div v-if="importDataModal.message" class="alert" :class="importDataModal.success ? 'alert-success' : 'alert-danger'">{{ importDataModal.message }}</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" @click="closeImportDataModal">Close</button>
                <button type="button" class="btn btn-primary" @click="submitImport" :disabled="importSending || !importDataModal.file">
                  {{ importSending ? 'Importing...' : 'Import Data' }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Export Data Modal -->
        <div v-show="exportDataModal.show" class="incoming-feed-modal" tabindex="-1" @click.self="closeExportDataModal">
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
                <label>
                  <input v-model="exportDataModal.includeRejects" type="checkbox" /> Include rejected records
                </label>
              </div>
              <div v-if="exportDataModal.message" class="alert" :class="exportDataModal.success ? 'alert-success' : 'alert-info'">{{ exportDataModal.message }}</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" @click="closeExportDataModal">Close</button>
                <button type="button" class="btn btn-primary" @click="submitExport" :disabled="exportSending">{{ exportSending ? 'Exporting...' : 'Export Data' }}</button>
              </div>
            </div>
          </div>
        </div>

        <!-- URL Report Modal -->
        <div v-show="urlReportModal.show" class="incoming-feed-modal" tabindex="-1" @click.self="closeUrlReportModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">URL Report – {{ urlReportModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeUrlReportModal">&times;</button>
              </div>
              <div class="modal-body">
                <p class="text-muted">Period goes from midnight of the first date to midnight of the second date. Leave blank to select from all time records.</p>
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

        <!-- Connect Outgoing Feeds Modal -->
        <div v-show="outgoingFeedsModal.show" class="incoming-feed-modal" tabindex="-1" @click.self="closeOutgoingFeedsModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">Connected Outgoing Feeds – {{ outgoingFeedsModal.feedLabel || '' }}</h4>
                <button type="button" class="close" @click="closeOutgoingFeedsModal">&times;</button>
              </div>
              <div class="modal-body">
                <p class="text-muted">Leads from this incoming feed are sent to these outgoing feeds in order. If one rejects, the next is tried (waterfall).</p>
                <div v-if="outgoingFeedsLoading" class="text-center">Loading...</div>
                <template v-else>
                  <p>
                    <button type="button" class="btn btn-primary btn-sm" @click="openAddOutgoingFeedModal">
                      Add Outgoing Feed
                    </button>
                  </p>
                  <div v-if="outgoingFeedsPopulations.length" class="table-responsive">
                    <table class="table table-bordered table-condensed table-striped">
                      <thead>
                        <tr class="bgGray">
                          <th>Order</th>
                          <th>Outgoing Feed</th>
                          <th>Queue Type</th>
                          <th>Status</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="p in outgoingFeedsPopulations" :key="p.idAssoc">
                          <td>{{ p.order ?? '—' }}</td>
                          <td>{{ p.populatingFeed || p.outboundLabel }}</td>
                          <td>{{ p.queueType }}</td>
                          <td>
                            <label class="switch">
                              <input
                                type="checkbox"
                                :checked="p.enabled === '1'"
                                @change="toggleOutgoingFeedPopulation(p.idAssoc)"
                              />
                              <span class="slider"></span>
                            </label>
                            <span class="ml-1">{{ p.enabled === '1' ? 'Enabled' : 'Disabled' }}</span>
                          </td>
                          <td>
                            <button type="button" class="btn btn-xs btn-default" @click="openEditOutgoingFeedModal(p)">Edit</button>
                            <button type="button" class="btn btn-xs btn-danger" @click="deleteOutgoingFeedPopulation(p.idAssoc)">Remove</button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <p v-else>No outgoing feeds connected. Click "Add Outgoing Feed" to connect one.</p>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Add/Edit Outgoing Feed Modal -->
        <div v-show="addOutgoingFeedModal.show" class="incoming-feed-modal add-population-modal" tabindex="-1" @click.self="closeAddOutgoingFeedModal">
          <div class="modal-dialog modal-lg" @click.stop>
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title">{{ addOutgoingFeedModal.editId ? 'Edit outgoing feed' : 'Add Outgoing Feed' }}</h4>
                <button type="button" class="close" @click="closeAddOutgoingFeedModal">&times;</button>
              </div>
              <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div v-if="!addOutgoingFeedModal.editId" class="add-pop-row">
                  <div class="add-pop-heading">Outgoing Feed</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Select outgoing feeds and click Add to add them to the list below. Click "Save changes" to save all to the database.</p>
                    <div class="d-flex align-items-center mb-2" style="flex-wrap: nowrap; gap: 10px; display: flex;">
                      <select v-model="addOutgoingFeedModal.idFeedOut" class="form-control" style="width: 300px; max-width: 300px; flex: 1 1 auto;">
                        <option value="">Select outgoing feed...</option>
                        <option v-for="f in availableOutboundFeedsForPopulation" :key="f.idFeedOut" :value="f.idFeedOut">
                          {{ f.displayLabel }}
                        </option>
                      </select>
                      <button type="button" class="btn btn-primary btn-sm" @click="addToPendingOutgoingFeeds" :disabled="!addOutgoingFeedModal.idFeedOut" style="flex-shrink: 0;">
                        Add
                      </button>
                    </div>
                    <p v-if="availableOutboundFeedsForPopulation.length === 0 && outboundFeedsForPopulation.length > 0" class="add-pop-desc text-muted mt-2">All outgoing feeds are already connected.</p>
                    <div v-if="pendingOutgoingFeeds.length" class="mt-3">
                      <strong>To be saved ({{ pendingOutgoingFeeds.length }}):</strong>
                      <table class="table table-bordered table-condensed table-striped mt-2">
                        <thead><tr><th>Order</th><th>Outgoing Feed</th><th>Queue Type</th><th></th></tr></thead>
                        <tbody>
                          <tr v-for="(item, idx) in pendingOutgoingFeeds" :key="item.tempId">
                            <td>{{ idx + 1 }}</td>
                            <td>{{ item.displayLabel }}</td>
                            <td>{{ item.queueType }}</td>
                            <td><button type="button" class="btn btn-xs btn-danger" @click="removeFromPendingOutgoingFeeds(item.tempId)">Remove</button></td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                <div v-else class="add-pop-row">
                  <div class="add-pop-heading">Outgoing Feed</div>
                  <div class="add-pop-content">
                    <p class="add-pop-readonly">{{ addOutgoingFeedModal.editFeedLabel || '—' }}</p>
                  </div>
                </div>

                <!-- Queue Type -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Queue Type</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Incoming records will be sent to this provider in REAL TIME as they come in. Do not use this option unless authorized. Most feeds have this option disabled.</p>
                    <div class="add-pop-queue-options">
                      <label class="add-pop-queue-opt"><input type="radio" v-model="addOutgoingFeedModal.queueType" value="livedata" /> Live Data (leads sent in real-time) [DEFAULT]</label>
                      <label class="add-pop-queue-opt"><input type="radio" v-model="addOutgoingFeedModal.queueType" value="queue" /> Standard Queue</label>
                      <label class="add-pop-queue-opt"><input type="radio" v-model="addOutgoingFeedModal.queueType" value="waterfall" /> Waterfall Live Standard (attempt each vendor in order; stop after the first accepted response)</label>
                      <label class="add-pop-queue-opt"><input type="radio" v-model="addOutgoingFeedModal.queueType" value="waterfallLimit" /> Waterfall Limit &amp; Queue (attempt vendors in priority order and queue; only skip to the next after the feed limits are hit)</label>
                      <label class="add-pop-queue-opt"><input type="radio" v-model="addOutgoingFeedModal.queueType" value="waterfallLimitLive" /> Waterfall Limit Live (attempt vendors in real-time in priority order; only skip to the next after the feed limits are hit)</label>
                    </div>
                  </div>
                </div>

                <!-- URL Filter Options -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">URL Filter Options</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Using the 'Accept' option, urls that are listed here are the only ones that will be accepted into the feed. Using the 'Reject' option, all urls will be accepted, except the ones listed here.</p>
                    <div class="add-pop-radios">
                      <label class="add-pop-radio"><input type="radio" v-model="addOutgoingFeedModal.filterTypeUrl" value="" /> Disabled</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addOutgoingFeedModal.filterTypeUrl" value="accept" /> Accept</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addOutgoingFeedModal.filterTypeUrl" value="reject" /> Reject</label>
                    </div>
                    <input v-if="addOutgoingFeedModal.filterTypeUrl" v-model="addOutgoingFeedModal.filterUrl" type="text" class="form-control add-pop-input" placeholder="URLs (semicolon-separated)" />
                  </div>
                </div>

                <!-- Email Filter Options -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Email Filter Options</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Using the 'Accept' option, email domains that are listed here are the only ones that will be accepted into the feed. Using the 'Reject' option, all email domains will be accepted, except the ones listed here.</p>
                    <div class="add-pop-radios">
                      <label class="add-pop-radio"><input type="radio" v-model="addOutgoingFeedModal.filterTypeEmail" value="" /> Disabled</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addOutgoingFeedModal.filterTypeEmail" value="accept" /> Accept</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addOutgoingFeedModal.filterTypeEmail" value="reject" /> Reject</label>
                    </div>
                    <input v-if="addOutgoingFeedModal.filterTypeEmail" v-model="addOutgoingFeedModal.filterEmail" type="text" class="form-control add-pop-input" placeholder="Email domains (semicolon-separated)" />
                  </div>
                </div>

                <!-- Listcode Filter Options -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Listcode Filter Options</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Using the 'Accept' option, listcodes that are listed here are the only ones that will be accepted into the feed. Using the 'Reject' option, all listcodes will be accepted, except the ones listed here.</p>
                    <div class="add-pop-radios">
                      <label class="add-pop-radio"><input type="radio" v-model="addOutgoingFeedModal.filterTypeListcode" value="" /> Disabled</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addOutgoingFeedModal.filterTypeListcode" value="accept" /> Accept</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addOutgoingFeedModal.filterTypeListcode" value="reject" /> Reject</label>
                    </div>
                    <input v-if="addOutgoingFeedModal.filterTypeListcode" v-model="addOutgoingFeedModal.filterListcode" type="text" class="form-control add-pop-input" placeholder="Listcodes (semicolon-separated)" />
                  </div>
                </div>

                <!-- Force URL Options -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Force URL Options</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Utilizing 'URL Forcing' changes the url listed in the incoming feed to a completely different URL for use in the outgoing feed.</p>
                    <div class="add-pop-radios">
                      <label class="add-pop-radio"><input type="radio" v-model="addOutgoingFeedModal.forceUrl" value="0" /> Disabled</label>
                      <label class="add-pop-radio"><input type="radio" v-model="addOutgoingFeedModal.forceUrl" value="1" /> Enabled</label>
                    </div>
                    <input v-if="addOutgoingFeedModal.forceUrl === '1'" v-model="addOutgoingFeedModal.forceUrlList" type="text" class="form-control add-pop-input" placeholder="Force URL mappings (semicolon-separated)" />
                  </div>
                </div>

                <!-- Waterfall Priority -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Waterfall Priority</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">Only applies if the Queue Type setting above is set to "Waterfall" or "Waterfall Limit". Use any number from 0 to 65,535. A higher number means higher priority in the waterfall. (Secondary to Order when Order is set.)</p>
                    <input v-model.number="addOutgoingFeedModal.waterfallPriority" type="number" min="0" max="65535" class="form-control add-pop-input add-pop-input-sm" placeholder="Waterfall Priority" />
                  </div>
                </div>

                <!-- Population Start Date -->
                <div class="add-pop-row">
                  <div class="add-pop-heading">Population Start Date</div>
                  <div class="add-pop-content">
                    <p class="add-pop-desc">If a value is filled in here, then records will not start populating this queue until midnight of the date provided.</p>
                    <input v-model="addOutgoingFeedModal.startDate" type="date" class="form-control add-pop-input add-pop-input-sm" />
                  </div>
                </div>

                <div v-if="addOutgoingFeedModal.editId" class="add-pop-row">
                  <div class="add-pop-heading"></div>
                  <div class="add-pop-content">
                    <label class="add-pop-checkbox"><input v-model="addOutgoingFeedModal.enabled" type="checkbox" true-value="1" false-value="0" /> Enabled</label>
                  </div>
                </div>

                <div v-if="addOutgoingFeedModalError" class="alert alert-danger">{{ addOutgoingFeedModalError }}</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" @click="closeAddOutgoingFeedModal">Close</button>
                <button type="button" class="btn btn-primary" @click="saveOutgoingFeed" :disabled="addOutgoingFeedSaving || (!addOutgoingFeedModal.editId && pendingOutgoingFeeds.length === 0)">
                  {{ addOutgoingFeedSaving ? 'Saving...' : (addOutgoingFeedModal.editId ? 'Save changes' : 'Save changes') }}
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
import InboundFeedForm from './InboundFeedForm.vue';
import QuickJump from './QuickJump.vue';

export default {
  name: 'IncomingFeedsManagement',
  components: {
    Navigation,
    InboundFeedForm,
    QuickJump,
  },
  setup() {
    const companyGroups = ref([]);
    const companies = ref([]);
    const availableFields = ref([]);
    const feedCategories = ref({});
    const timezones = ref([]);
    const staffUsers = ref([]);
    const loading = ref(false);
    const adding = ref(false);
    const updating = ref(false);
    const addError = ref('');
    const editError = ref('');
    const expandedCompanies = ref({});
    const openDropdownFeedId = ref(null);

    const toggleFeedDropdown = (idFeedIn) => {
      openDropdownFeedId.value = openDropdownFeedId.value === idFeedIn ? null : idFeedIn;
    };
    const editingFeed = reactive({
      idFeedIn: null,
      label: '',
      description: '',
      idCompany: '',
      filterState: '',
      filterStateChoice: [],
      filterZip: '',
      filterZipCodes: [],
      feedCategory: 'phone',
      timezone: 'America/New_York',
      requiredPingFields: [],
      allowedPingFields: [],
      required: ['email', 'ip', 'url', 'stamp'],
      allowedFields: [],
      custom1Label: '',
      custom2Label: '',
      custom3Label: '',
      custom4Label: '',
      custom5Label: '',
      custom6Label: '',
      dedupeEmail: '0',
      dedupeLandline: '0',
      dedupeCellphone: '0',
      dedupeAcross: 'urlGlobal',
      lookbackPeriod: '120',
      filterTypeUrl: '',
      filterUrl: [],
      rejectOldLeadsMaxAge: '7 Days Ago',
      pingTimeout: '300',
      dailyLimit: '',
      chokePercent: '0',
      costPerLead: '',
      salesperson: '',
      notifications: '1',
      notifyThresholdCount: '0',
      notifyThresholdTime: '',
      notifyThresholdDays: [],
      pauseMessage: '',
      timeskew: '',
      status: 'active',
      minimumBirthAge: '',
      maximumBirthAge: '',
    });

    const filters = reactive({
      status: 'active',
      feedCategory: 'phone',
      statsStart: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      statsEnd: new Date().toISOString().split('T')[0],
    });

    const usStates = {
      AL: 'Alabama',
      AK: 'Alaska',
      AZ: 'Arizona',
      AR: 'Arkansas',
      CA: 'California',
      CO: 'Colorado',
      CT: 'Connecticut',
      DE: 'Delaware',
      DC: 'District of Columbia',
      FL: 'Florida',
      GA: 'Georgia',
      HI: 'Hawaii',
      ID: 'Idaho',
      IL: 'Illinois',
      IN: 'Indiana',
      IA: 'Iowa',
      KS: 'Kansas',
      KY: 'Kentucky',
      LA: 'Louisiana',
      ME: 'Maine',
      MD: 'Maryland',
      MA: 'Massachusetts',
      MI: 'Michigan',
      MN: 'Minnesota',
      MS: 'Mississippi',
      MO: 'Missouri',
      MT: 'Montana',
      NE: 'Nebraska',
      NV: 'Nevada',
      NH: 'New Hampshire',
      NJ: 'New Jersey',
      NM: 'New Mexico',
      NY: 'New York',
      NC: 'North Carolina',
      ND: 'North Dakota',
      OH: 'Ohio',
      OK: 'Oklahoma',
      OR: 'Oregon',
      PA: 'Pennsylvania',
      RI: 'Rhode Island',
      SC: 'South Carolina',
      SD: 'South Dakota',
      TN: 'Tennessee',
      TX: 'Texas',
      UT: 'Utah',
      VT: 'Vermont',
      VA: 'Virginia',
      WA: 'Washington',
      WV: 'West Virginia',
      WI: 'Wisconsin',
      WY: 'Wyoming',
    };

    const newFeed = reactive({
      label: '',
      description: '',
      idCompany: '',
      filterState: '',
      filterStateChoice: [],
      filterZip: '',
      filterZipCodes: [],
      feedCategory: 'phone',
      timezone: 'America/New_York',
      requiredPingFields: [],
      allowedPingFields: [],
      required: ['email', 'ip', 'url', 'stamp'],
      allowedFields: [],
      custom1Label: '',
      custom2Label: '',
      custom3Label: '',
      custom4Label: '',
      custom5Label: '',
      custom6Label: '',
      dedupeEmail: '0',
      dedupeLandline: '0',
      dedupeCellphone: '0',
      dedupeAcross: 'urlGlobal',
      lookbackPeriod: '120',
      filterTypeUrl: '',
      filterUrl: [],
      rejectOldLeadsMaxAge: '7 Days Ago',
      pingTimeout: '300',
      dailyLimit: '',
      chokePercent: '0',
      costPerLead: '',
      salesperson: '',
      notifications: '1',
      notifyThresholdCount: '0',
      notifyThresholdTime: '',
      notifyThresholdDays: [],
      pauseMessage: '',
      timeskew: '',
      status: 'active',
      minimumBirthAge: '',
      maximumBirthAge: '',
    });

    const formatNumber = (num) => {
      return new Intl.NumberFormat().format(num);
    };

    const recordSearchLink = (idCompany, idFeedIn, status) => {
      const query = {
        startDate: filters.statsStart,
        endDate: filters.statsEnd,
        status,
      };
      if (idCompany) query.idCompany = idCompany;
      if (idFeedIn) query.idFeedIn = idFeedIn;
      return { path: '/record-search', query };
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

        const response = await axios.get('/api/inbound-feeds', { params });
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

    const togglePause = async (idFeedIn, paused) => {
      try {
        const r = await axios.patch(`/api/inbound-feeds/${idFeedIn}/toggle-pause`, {
          paused: paused ? 1 : 0,
        });
        if (r.data.status === 1) {
          await fetchFeeds();
        } else {
          alert(r.data.error || 'Failed to toggle pause');
        }
      } catch (error) {
        console.error('Error toggling pause:', error);
        alert(error.response?.data?.error || error.message || 'Failed to toggle pause');
      }
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

    const apiSpecModal = reactive({ show: false, feedLabel: '', data: null });
    const apiSpecLoading = ref(false);

    const importDataModal = reactive({
      show: false,
      idFeedIn: null,
      feedLabel: '',
      allowedFields: [],
      requiredFields: [],
      fieldMapping: {},
      file: null,
      message: '',
      success: false,
    });
    const importColumnOptions = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
    const importFileInput = ref(null);
    const importSending = ref(false);

    const exportDataModal = reactive({
      show: false,
      idFeedIn: null,
      feedLabel: '',
      dateStart: new Date().toISOString().split('T')[0],
      dateEnd: new Date(Date.now() + 86400000).toISOString().split('T')[0],
      limit: '',
      includeRejects: false,
      message: '',
      success: false,
    });
    const exportColumns = ref([]);
    const exportSending = ref(false);

    const urlReportModal = reactive({
      show: false,
      idFeedIn: null,
      feedLabel: '',
      dateStart: new Date().toISOString().split('T')[0],
      dateEnd: new Date(Date.now() + 86400000).toISOString().split('T')[0],
      breakdown: 'day',
      sort: 'date',
      group: 'date',
      urlList: [],
    });
    const urlReportResults = ref([]);
    const urlReportUrlList = ref([]);
    const urlReportLoading = ref(false);

    const outgoingFeedsModal = reactive({ show: false, idFeedIn: null, feedLabel: '' });
    const outgoingFeedsPopulations = ref([]);
    const outgoingFeedsLoading = ref(false);
    const outboundFeedsForPopulation = ref([]);
    const pendingOutgoingFeeds = ref([]);
    let tempIdCounter = 0;
    const availableOutboundFeedsForPopulation = computed(() => {
      const connectedIds = outgoingFeedsPopulations.value.map((p) => p.idFeedOut);
      const pendingIds = pendingOutgoingFeeds.value.map((p) => p.idFeedOut);
      return outboundFeedsForPopulation.value.filter((f) => !connectedIds.includes(f.idFeedOut) && !pendingIds.includes(f.idFeedOut));
    });
    const addOutgoingFeedModal = reactive({
      show: false,
      editId: null,
      editFeedLabel: '',
      idFeedOut: '',
      order: 1,
      queueType: 'waterfall',
      enabled: '1',
      filterTypeUrl: '',
      filterUrl: '',
      filterTypeEmail: '',
      filterEmail: '',
      filterTypeListcode: '',
      filterListcode: '',
      forceUrl: '0',
      forceUrlList: '',
      waterfallPriority: 0,
      startDate: '',
    });
    const addOutgoingFeedModalError = ref('');
    const addOutgoingFeedSaving = ref(false);

    const openOutgoingFeedsModal = async (feed) => {
      outgoingFeedsModal.idFeedIn = feed.idFeedIn;
      outgoingFeedsModal.feedLabel = feed.label;
      outgoingFeedsModal.show = true;
    };

    const closeOutgoingFeedsModal = () => {
      outgoingFeedsModal.show = false;
      outgoingFeedsModal.idFeedIn = null;
      outgoingFeedsModal.feedLabel = '';
    };

    const fetchOutgoingFeedsPopulations = async () => {
      if (!outgoingFeedsModal.idFeedIn) return;
      outgoingFeedsLoading.value = true;
      try {
        const r = await axios.get(`/api/inbound-feeds/${outgoingFeedsModal.idFeedIn}/populations`);
        outgoingFeedsPopulations.value = r.data.status === 1 ? r.data.data : [];
      } catch (e) {
        outgoingFeedsPopulations.value = [];
      } finally {
        outgoingFeedsLoading.value = false;
      }
    };

    const fetchOutboundFeedsForPopulation = async () => {
      try {
        const r = await axios.get('/api/feed-populations/outbound-feeds');
        outboundFeedsForPopulation.value = r.data.status === 1 ? r.data.data : [];
      } catch (e) {
        outboundFeedsForPopulation.value = [];
      }
    };

    watch(() => outgoingFeedsModal.show, (show) => {
      if (show && outgoingFeedsModal.idFeedIn) {
        fetchOutgoingFeedsPopulations();
        fetchOutboundFeedsForPopulation();
      }
    });

    const openAddOutgoingFeedModal = () => {
      addOutgoingFeedModal.editId = null;
      addOutgoingFeedModal.editFeedLabel = '';
      addOutgoingFeedModal.idFeedOut = '';
      addOutgoingFeedModal.queueType = 'waterfall';
      addOutgoingFeedModal.enabled = '1';
      addOutgoingFeedModal.filterTypeUrl = '';
      addOutgoingFeedModal.filterUrl = '';
      addOutgoingFeedModal.filterTypeEmail = '';
      addOutgoingFeedModal.filterEmail = '';
      addOutgoingFeedModal.filterTypeListcode = '';
      addOutgoingFeedModal.filterListcode = '';
      addOutgoingFeedModal.forceUrl = '0';
      addOutgoingFeedModal.forceUrlList = '';
      addOutgoingFeedModal.waterfallPriority = 0;
      addOutgoingFeedModal.startDate = '';
      addOutgoingFeedModalError.value = '';
      pendingOutgoingFeeds.value = [];
      addOutgoingFeedModal.show = true;
    };

    const closeAddOutgoingFeedModal = () => {
      addOutgoingFeedModal.show = false;
      addOutgoingFeedModal.editId = null;
      addOutgoingFeedModalError.value = '';
      pendingOutgoingFeeds.value = [];
    };

    const addToPendingOutgoingFeeds = () => {
      if (!addOutgoingFeedModal.idFeedOut) return;
      const feed = outboundFeedsForPopulation.value.find((f) => f.idFeedOut === addOutgoingFeedModal.idFeedOut);
      const displayLabel = feed ? feed.displayLabel : String(addOutgoingFeedModal.idFeedOut);
      pendingOutgoingFeeds.value.push({
        tempId: ++tempIdCounter,
        idFeedOut: addOutgoingFeedModal.idFeedOut,
        displayLabel,
        queueType: addOutgoingFeedModal.queueType,
        enabled: addOutgoingFeedModal.enabled,
        filterTypeUrl: addOutgoingFeedModal.filterTypeUrl || null,
        filterUrl: addOutgoingFeedModal.filterTypeUrl ? addOutgoingFeedModal.filterUrl : null,
        filterTypeEmail: addOutgoingFeedModal.filterTypeEmail || null,
        filterEmail: addOutgoingFeedModal.filterTypeEmail ? addOutgoingFeedModal.filterEmail : null,
        filterTypeListcode: addOutgoingFeedModal.filterTypeListcode || null,
        filterListcode: addOutgoingFeedModal.filterTypeListcode ? addOutgoingFeedModal.filterListcode : null,
        forceUrl: addOutgoingFeedModal.forceUrl === '1' ? 1 : 0,
        forceUrlList: addOutgoingFeedModal.forceUrl === '1' ? addOutgoingFeedModal.forceUrlList : null,
        waterfallPriority: addOutgoingFeedModal.waterfallPriority,
        startDate: addOutgoingFeedModal.startDate || null,
      });
      addOutgoingFeedModal.idFeedOut = '';
    };

    const removeFromPendingOutgoingFeeds = (tempId) => {
      pendingOutgoingFeeds.value = pendingOutgoingFeeds.value.filter((p) => p.tempId !== tempId);
    };

    const openEditOutgoingFeedModal = (p) => {
      addOutgoingFeedModal.editId = p.idAssoc;
      addOutgoingFeedModal.editFeedLabel = p.populatingFeed || p.outboundLabel;
      addOutgoingFeedModal.idFeedOut = p.idFeedOut;
      addOutgoingFeedModal.queueType = p.queueType || 'waterfall';
      addOutgoingFeedModal.enabled = p.enabled || '1';
      addOutgoingFeedModal.filterTypeUrl = p.filterTypeUrl || '';
      addOutgoingFeedModal.filterUrl = p.filterUrl || '';
      addOutgoingFeedModal.filterTypeEmail = p.filterTypeEmail || '';
      addOutgoingFeedModal.filterEmail = p.filterEmail || '';
      addOutgoingFeedModal.filterTypeListcode = p.filterTypeListcode || '';
      addOutgoingFeedModal.filterListcode = p.filterListcode || '';
      addOutgoingFeedModal.forceUrl = p.forceUrl ? '1' : '0';
      addOutgoingFeedModal.forceUrlList = p.forceUrlList || '';
      addOutgoingFeedModal.waterfallPriority = p.waterfallPriority ?? 0;
      addOutgoingFeedModal.startDate = p.startDate ? p.startDate.split(' ')[0] : '';
      addOutgoingFeedModalError.value = '';
      addOutgoingFeedModal.show = true;
    };

    const saveOutgoingFeed = async () => {
      if (!outgoingFeedsModal.idFeedIn) return;
      addOutgoingFeedSaving.value = true;
      addOutgoingFeedModalError.value = '';
      try {
        if (addOutgoingFeedModal.editId) {
          const r = await axios.put(`/api/feed-populations/${addOutgoingFeedModal.editId}`, {
            queueType: addOutgoingFeedModal.queueType,
            enabled: addOutgoingFeedModal.enabled,
            waterfallPriority: addOutgoingFeedModal.waterfallPriority,
            startDate: addOutgoingFeedModal.startDate || null,
            filterTypeUrl: addOutgoingFeedModal.filterTypeUrl || null,
            filterUrl: addOutgoingFeedModal.filterTypeUrl ? addOutgoingFeedModal.filterUrl : null,
            filterTypeEmail: addOutgoingFeedModal.filterTypeEmail || null,
            filterEmail: addOutgoingFeedModal.filterTypeEmail ? addOutgoingFeedModal.filterEmail : null,
            filterTypeListcode: addOutgoingFeedModal.filterTypeListcode || null,
            filterListcode: addOutgoingFeedModal.filterTypeListcode ? addOutgoingFeedModal.filterListcode : null,
            forceUrl: addOutgoingFeedModal.forceUrl === '1' ? 1 : 0,
            forceUrlList: addOutgoingFeedModal.forceUrl === '1' ? addOutgoingFeedModal.forceUrlList : null,
          });
          if (r.data.status === 1) {
            closeAddOutgoingFeedModal();
            await fetchOutgoingFeedsPopulations();
          } else {
            addOutgoingFeedModalError.value = r.data.error || 'Update failed';
          }
        } else {
          const baseOrder = outgoingFeedsPopulations.value.length
            ? Math.max(...outgoingFeedsPopulations.value.map((p) => p.order || 0), 0)
            : 0;
          for (let i = 0; i < pendingOutgoingFeeds.value.length; i++) {
            const item = pendingOutgoingFeeds.value[i];
            const r = await axios.post(`/api/inbound-feeds/${outgoingFeedsModal.idFeedIn}/populations`, {
              idFeedOut: item.idFeedOut,
              order: baseOrder + i + 1,
              queueType: item.queueType,
              enabled: item.enabled,
              waterfallPriority: item.waterfallPriority,
              startDate: item.startDate,
              filterTypeUrl: item.filterTypeUrl,
              filterUrl: item.filterUrl,
              filterTypeEmail: item.filterTypeEmail,
              filterEmail: item.filterEmail,
              filterTypeListcode: item.filterTypeListcode,
              filterListcode: item.filterListcode,
              forceUrl: item.forceUrl,
              forceUrlList: item.forceUrlList,
            });
            if (r.data.status !== 1) {
              addOutgoingFeedModalError.value = r.data.error || 'Add failed';
              return;
            }
          }
          closeAddOutgoingFeedModal();
          await fetchOutgoingFeedsPopulations();
        }
      } catch (e) {
        addOutgoingFeedModalError.value = e.response?.data?.error || e.message || 'Request failed';
      } finally {
        addOutgoingFeedSaving.value = false;
      }
    };

    const toggleOutgoingFeedPopulation = async (idAssoc) => {
      try {
        const r = await axios.patch(`/api/feed-populations/${idAssoc}/toggle`);
        if (r.data.status === 1) await fetchOutgoingFeedsPopulations();
      } catch (e) {
        console.error('Toggle failed:', e);
      }
    };

    const deleteOutgoingFeedPopulation = async (idAssoc) => {
      if (!confirm('Remove this outgoing feed connection?')) return;
      try {
        const r = await axios.delete(`/api/feed-populations/${idAssoc}`);
        if (r.data.status === 1) await fetchOutgoingFeedsPopulations();
      } catch (e) {
        console.error('Delete failed:', e);
      }
    };

    const openApiSpec = async (feed) => {
      apiSpecModal.feedLabel = feed.label;
      apiSpecModal.show = true;
      apiSpecModal.data = null;
      apiSpecLoading.value = true;
      try {
        const r = await axios.get(`/api/inbound-feeds/${feed.idFeedIn}/api-spec`);
        if (r.data.status === 1) apiSpecModal.data = r.data.data;
      } catch (e) {
        apiSpecModal.data = { error: e.response?.data?.error || e.message };
      } finally {
        apiSpecLoading.value = false;
      }
    };

    const copyToClipboard = async (text) => {
      try {
        await navigator.clipboard.writeText(text);
        alert('Copied to clipboard');
      } catch {
        alert('Could not copy');
      }
    };

    const closeApiSpecModal = () => {
      apiSpecModal.show = false;
      apiSpecModal.data = null;
    };

    const openImportDataModal = async (feed) => {
      importDataModal.idFeedIn = feed.idFeedIn;
      importDataModal.feedLabel = feed.label;
      importDataModal.file = null;
      importDataModal.message = '';
      importDataModal.show = true;
      try {
        const r = await axios.get(`/api/inbound-feeds/${feed.idFeedIn}`);
        if (r.data.status === 1) {
          const d = r.data.data;
          const allowed = Array.isArray(d.allowedFields) ? d.allowedFields : (d.allowedFields ? d.allowedFields.split(';') : []);
          const required = Array.isArray(d.required) ? d.required : (d.required ? d.required.split(';') : []);
          if (allowed.indexOf('stamp') !== -1) allowed.splice(allowed.indexOf('stamp') + 1, 0, 'time');
          importDataModal.allowedFields = allowed;
          importDataModal.requiredFields = required;
          importDataModal.fieldMapping = {};
          allowed.forEach((f) => { importDataModal.fieldMapping[f] = ''; });
        }
      } catch (e) {
        importDataModal.allowedFields = [];
        importDataModal.requiredFields = [];
      }
      if (importFileInput.value) importFileInput.value.value = '';
    };

    const onImportFileSelect = (e) => {
      importDataModal.file = e.target.files?.[0] || null;
      importDataModal.message = '';
    };

    const closeImportDataModal = () => {
      importDataModal.show = false;
      importDataModal.file = null;
    };

    const submitImport = async () => {
      if (!importDataModal.idFeedIn || !importDataModal.file) return;
      const required = importDataModal.requiredFields || [];
      const mapping = importDataModal.fieldMapping || {};
      for (const r of required) {
        if (mapping[r] === '' || mapping[r] === undefined) {
          importDataModal.message = `Required field "${r}" must be mapped to a column.`;
          importDataModal.success = false;
          return;
        }
      }
      importSending.value = true;
      importDataModal.message = '';
      try {
        const formData = new FormData();
        formData.append('file', importDataModal.file);
        Object.entries(mapping).forEach(([field, colIdx]) => {
          if (colIdx !== '' && colIdx !== undefined && colIdx !== null) formData.append(`field_${field}`, String(colIdx));
        });
        const r = await axios.post(`/api/inbound-feeds/${importDataModal.idFeedIn}/import`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        importDataModal.message = r.data.message || (r.data.status === 1 ? `Imported ${r.data.imported || 0} records.` : r.data.error);
        importDataModal.success = r.data.status === 1;
      } catch (e) {
        importDataModal.message = e.response?.data?.error || e.message || 'Import failed';
        importDataModal.success = false;
      } finally {
        importSending.value = false;
      }
    };

    const openExportDataModal = async (feed) => {
      exportDataModal.idFeedIn = feed.idFeedIn;
      exportDataModal.feedLabel = feed.label;
      exportDataModal.message = '';
      exportDataModal.show = true;
      try {
        const r = await axios.get(`/api/inbound-feeds/${feed.idFeedIn}/export-columns`);
        exportColumns.value = (r.data.status === 1 ? r.data.data : []).map((f) => ({ ...f, checked: true }));
      } catch (e) {
        exportColumns.value = [];
      }
    };

    const closeExportDataModal = () => {
      exportDataModal.show = false;
    };

    const submitExport = async () => {
      if (!exportDataModal.idFeedIn) return;
      const cols = exportColumns.value.filter((c) => c.checked).map((c) => c.fieldName);
      if (!cols.length) {
        exportDataModal.message = 'Select at least one column to export.';
        exportDataModal.success = false;
        return;
      }
      exportSending.value = true;
      exportDataModal.message = '';
      try {
        const r = await axios.post(`/api/inbound-feeds/${exportDataModal.idFeedIn}/export`, {
          columns: cols,
          dateStart: exportDataModal.dateStart,
          dateEnd: exportDataModal.dateEnd,
          limit: exportDataModal.limit ? parseInt(exportDataModal.limit) : null,
          includeRejects: exportDataModal.includeRejects,
        }, { responseType: 'blob' });
        const blob = new Blob([r.data], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `inbound_${exportDataModal.idFeedIn}_${Date.now()}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
        exportDataModal.message = 'Export downloaded successfully.';
        exportDataModal.success = true;
      } catch (e) {
        if (e.response?.data instanceof Blob) {
          const text = await e.response.data.text();
          try {
            const err = JSON.parse(text);
            exportDataModal.message = err.error || 'Export failed';
          } catch {
            exportDataModal.message = text || 'Export failed';
          }
        } else {
          exportDataModal.message = e.response?.data?.error || e.message || 'Export failed';
        }
        exportDataModal.success = false;
      } finally {
        exportSending.value = false;
      }
    };

    const openUrlReportModal = async (feed) => {
      urlReportModal.idFeedIn = feed.idFeedIn;
      urlReportModal.feedLabel = feed.label;
      urlReportModal.dateStart = new Date().toISOString().split('T')[0];
      urlReportModal.dateEnd = new Date(Date.now() + 86400000).toISOString().split('T')[0];
      urlReportModal.breakdown = 'day';
      urlReportModal.sort = 'date';
      urlReportModal.group = 'date';
      urlReportModal.urlList = [];
      urlReportResults.value = [];
      urlReportModal.show = true;
      try {
        const r = await axios.get(`/api/inbound-feeds/${feed.idFeedIn}/url-list`);
        urlReportUrlList.value = r.data.status === 1 ? r.data.data : [];
      } catch (e) {
        urlReportUrlList.value = [];
      }
    };

    const closeUrlReportModal = () => {
      urlReportModal.show = false;
    };

    const runUrlReport = async () => {
      if (!urlReportModal.idFeedIn) return;
      urlReportLoading.value = true;
      try {
        const params = {
          dateStart: urlReportModal.dateStart,
          dateEnd: urlReportModal.dateEnd,
          breakdown: urlReportModal.breakdown,
          sort: urlReportModal.sort,
          group: urlReportModal.group,
        };
        if (urlReportModal.urlList && urlReportModal.urlList.length) {
          params.urlList = urlReportModal.urlList;
        }
        const r = await axios.get(`/api/inbound-feeds/${urlReportModal.idFeedIn}/url-report`, { params });
        urlReportResults.value = r.data.status === 1 ? r.data.data : [];
      } catch (e) {
        urlReportResults.value = [];
      } finally {
        urlReportLoading.value = false;
      }
    };

    const openEditModal = async (feed) => {
      editError.value = '';
      try {
        // Fetch full feed details
        const response = await axios.get(`/api/inbound-feeds/${feed.idFeedIn}`);
        if (response.data.status === 1) {
          const feedData = response.data.data;
          
          // Populate editingFeed with fetched data
          Object.assign(editingFeed, {
            idFeedIn: feedData.idFeedIn,
            label: feedData.label || '',
            description: feedData.description || '',
            idCompany: feedData.idCompany || '',
            filterState: feedData.filterState?.mode || '',
            filterStateChoice: feedData.filterState?.states || [],
            filterZip: feedData.filterZip?.mode || '',
            filterZipCodes: feedData.filterZip?.zipCodes || [],
            feedCategory: feedData.feedCategory || 'phone',
            timezone: feedData.timezone || 'America/New_York',
            requiredPingFields: feedData.requiredPingFields || [],
            allowedPingFields: feedData.allowedPingFields || [],
            required: feedData.required || ['email', 'ip', 'url', 'stamp'],
            allowedFields: feedData.allowedFields || [],
            custom1Label: feedData.custom1Label || '',
            custom2Label: feedData.custom2Label || '',
            custom3Label: feedData.custom3Label || '',
            custom4Label: feedData.custom4Label || '',
            custom5Label: feedData.custom5Label || '',
            custom6Label: feedData.custom6Label || '',
            dedupeEmail: feedData.dedupeEmail === '1' || feedData.dedupeEmail === 1 ? '1' : '0',
            dedupeLandline: feedData.dedupeLandline === '1' || feedData.dedupeLandline === 1 ? '1' : '0',
            dedupeCellphone: feedData.dedupeCellphone === '1' || feedData.dedupeCellphone === 1 ? '1' : '0',
            dedupeAcross: feedData.dedupeAcross || 'urlGlobal',
            lookbackPeriod: feedData.lookbackPeriod ? String(feedData.lookbackPeriod) : '120',
            filterTypeUrl: feedData.filterTypeUrl || '',
            filterUrl: feedData.filterUrl || [],
            rejectOldLeadsMaxAge: feedData.rejectOldLeadsMaxAge || '7 Days Ago',
            pingTimeout: feedData.pingTimeout ? String(feedData.pingTimeout) : '300',
            dailyLimit: feedData.dailyLimit ? String(feedData.dailyLimit) : '',
            chokePercent: feedData.chokePercent ? String(feedData.chokePercent) : '0',
            costPerLead: feedData.costPerLead ? String(feedData.costPerLead) : '',
            salesperson: feedData.salesperson ? String(feedData.salesperson) : '',
            notifications: feedData.notifications === '1' || feedData.notifications === 1 ? '1' : '0',
            notifyThresholdCount: feedData.notifyThresholdCount ? String(feedData.notifyThresholdCount) : '0',
            notifyThresholdTime: feedData.notifyThresholdTime || '',
            notifyThresholdDays: feedData.notifyThresholdDays || [],
            pauseMessage: feedData.pauseMessage || '',
            timeskew: feedData.timeskew || '',
            status: feedData.status || 'active',
            minimumBirthAge: feedData.minimumBirthAge ? String(feedData.minimumBirthAge) : '',
            maximumBirthAge: feedData.maximumBirthAge ? String(feedData.maximumBirthAge) : '',
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
        idFeedIn: null,
        label: '',
        description: '',
        idCompany: '',
        filterState: '',
        filterStateChoice: [],
        filterZip: '',
        filterZipCodes: [],
        feedCategory: 'phone',
        timezone: 'America/New_York',
        requiredPingFields: [],
        allowedPingFields: [],
        required: ['email', 'ip', 'url', 'stamp'],
        allowedFields: [],
        custom1Label: '',
        custom2Label: '',
        custom3Label: '',
        custom4Label: '',
        custom5Label: '',
        custom6Label: '',
        dedupeEmail: '0',
        dedupeLandline: '0',
        dedupeCellphone: '0',
        dedupeAcross: 'urlGlobal',
        lookbackPeriod: '120',
        filterTypeUrl: '',
        filterUrl: [],
        rejectOldLeadsMaxAge: '7 Days Ago',
        pingTimeout: '300',
        dailyLimit: '',
        chokePercent: '0',
        costPerLead: '',
        salesperson: '',
        notifications: '1',
        notifyThresholdCount: '0',
        notifyThresholdTime: '',
        notifyThresholdDays: [],
        pauseMessage: '',
        timeskew: '',
        status: 'active',
        minimumBirthAge: '',
        maximumBirthAge: '',
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
        const response = await axios.get('/api/inbound-feeds/available-fields');
        if (response.data.status === 1) {
          availableFields.value = response.data.data || [];
          // Set default allowedFields to all fields
          if (availableFields.value.length > 0) {
            newFeed.allowedFields = availableFields.value.map((f) => f.fieldName);
          }
        }
      } catch (error) {
        console.error('Error fetching available fields:', error);
      }
    };

    const fetchFeedCategories = async () => {
      try {
        const response = await axios.get('/api/inbound-feeds/categories');
        if (response.data.status === 1) {
          feedCategories.value = response.data.data || {};
        }
      } catch (error) {
        console.error('Error fetching feed categories:', error);
      }
    };

    const fetchTimezones = async () => {
      try {
        const response = await axios.get('/api/inbound-feeds/timezones');
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

    const handleAddFeed = async () => {
      if (!newFeed.label.trim()) {
        addError.value = 'Feed label cannot be empty.';
        return;
      }

      if (!newFeed.idCompany) {
        addError.value = 'Company cannot be empty.';
        return;
      }

      if (newFeed.allowedFields.length === 0) {
        addError.value = 'You must allow at least one field to be processed.';
        return;
      }

      if (newFeed.filterState && (newFeed.filterState === 'includeOnly' || newFeed.filterState === 'excludeOnly')) {
        if (newFeed.filterStateChoice.length === 0) {
          addError.value = 'If using the state filter feature, at least one state must be selected.';
          return;
        }
      }

      adding.value = true;
      addError.value = '';

      try {
        const payload = {
          label: newFeed.label.trim(),
          description: newFeed.description.trim() || null,
          idCompany: parseInt(newFeed.idCompany),
          feedCategory: newFeed.feedCategory,
          timezone: newFeed.timezone,
          required: newFeed.required,
          allowedFields: newFeed.allowedFields,
          requiredPingFields: newFeed.requiredPingFields,
          allowedPingFields: newFeed.allowedPingFields,
          custom1Label: newFeed.custom1Label.trim() || null,
          custom2Label: newFeed.custom2Label.trim() || null,
          custom3Label: newFeed.custom3Label.trim() || null,
          custom4Label: newFeed.custom4Label.trim() || null,
          custom5Label: newFeed.custom5Label.trim() || null,
          custom6Label: newFeed.custom6Label.trim() || null,
          dedupeEmail: newFeed.dedupeEmail,
          dedupeLandline: newFeed.dedupeLandline,
          dedupeCellphone: newFeed.dedupeCellphone,
          dedupeAcross: newFeed.dedupeAcross,
          lookbackPeriod: parseInt(newFeed.lookbackPeriod),
          filterTypeUrl: newFeed.filterTypeUrl || null,
          filterUrl: newFeed.filterUrl.filter((url) => url.trim()),
          filterState: newFeed.filterState || null,
          filterStateChoice: newFeed.filterStateChoice,
          filterZip: newFeed.filterZip || null,
          filterZipCodes: newFeed.filterZipCodes,
          rejectOldLeadsMaxAge: newFeed.rejectOldLeadsMaxAge.trim() || null,
          pingTimeout: newFeed.pingTimeout ? parseInt(newFeed.pingTimeout) : null,
          dailyLimit: newFeed.dailyLimit ? parseInt(newFeed.dailyLimit) : null,
          chokePercent: newFeed.chokePercent ? parseInt(newFeed.chokePercent) : 0,
          costPerLead: newFeed.costPerLead ? parseFloat(newFeed.costPerLead) : null,
          salesperson: newFeed.salesperson ? parseInt(newFeed.salesperson) : null,
          notifications: newFeed.notifications,
          notifyThresholdCount: newFeed.notifyThresholdCount ? parseInt(newFeed.notifyThresholdCount) : 0,
          notifyThresholdTime: newFeed.notifyThresholdTime.trim() || null,
          notifyThresholdDays: newFeed.notifyThresholdDays,
          pauseMessage: newFeed.pauseMessage.trim() || null,
          timeskew: newFeed.timeskew.trim() || null,
          status: newFeed.status,
          minimumBirthAge: newFeed.minimumBirthAge ? parseInt(newFeed.minimumBirthAge) : null,
          maximumBirthAge: newFeed.maximumBirthAge ? parseInt(newFeed.maximumBirthAge) : null,
        };

        const response = await axios.post('/api/inbound-feeds', payload);

        if (response.data.status === 1) {
          closeAddModal();
          // Reset form
          const defaultAllowedFields = availableFields.value.length > 0
            ? availableFields.value.map((f) => f.fieldName)
            : [];
          Object.assign(newFeed, {
            label: '',
            description: '',
            idCompany: '',
            filterState: '',
            filterStateChoice: [],
            filterZip: '',
            filterZipCodes: [],
            feedCategory: 'phone',
            timezone: 'America/New_York',
            requiredPingFields: [],
            allowedPingFields: [],
            required: ['email', 'ip', 'url', 'stamp'],
            allowedFields: defaultAllowedFields,
            custom1Label: '',
            custom2Label: '',
            custom3Label: '',
            custom4Label: '',
            custom5Label: '',
            custom6Label: '',
            dedupeEmail: '0',
            dedupeLandline: '0',
            dedupeCellphone: '0',
            dedupeAcross: 'urlGlobal',
            lookbackPeriod: '120',
            filterTypeUrl: '',
            filterUrl: [],
            rejectOldLeadsMaxAge: '7 Days Ago',
            pingTimeout: '300',
            dailyLimit: '',
            chokePercent: '0',
            costPerLead: '',
            salesperson: '',
            notifications: '1',
            notifyThresholdCount: '0',
            notifyThresholdTime: '',
            notifyThresholdDays: [],
            pauseMessage: '',
            timeskew: '',
            status: 'active',
            minimumBirthAge: '',
            maximumBirthAge: '',
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

    const updateEditingFeed = (updatedFeed) => {
      Object.assign(editingFeed, updatedFeed);
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

      if (editingFeed.allowedFields.length === 0) {
        editError.value = 'You must allow at least one field to be processed.';
        return;
      }

      if (editingFeed.filterState && (editingFeed.filterState === 'includeOnly' || editingFeed.filterState === 'excludeOnly')) {
        if (editingFeed.filterStateChoice.length === 0) {
          editError.value = 'If using the state filter feature, at least one state must be selected.';
          return;
        }
      }

      updating.value = true;
      editError.value = '';

      try {
        const payload = {
          label: editingFeed.label.trim(),
          description: editingFeed.description.trim() || null,
          idCompany: parseInt(editingFeed.idCompany),
          feedCategory: editingFeed.feedCategory,
          timezone: editingFeed.timezone,
          required: editingFeed.required,
          allowedFields: editingFeed.allowedFields,
          requiredPingFields: editingFeed.requiredPingFields,
          allowedPingFields: editingFeed.allowedPingFields,
          custom1Label: editingFeed.custom1Label.trim() || null,
          custom2Label: editingFeed.custom2Label.trim() || null,
          custom3Label: editingFeed.custom3Label.trim() || null,
          custom4Label: editingFeed.custom4Label.trim() || null,
          custom5Label: editingFeed.custom5Label.trim() || null,
          custom6Label: editingFeed.custom6Label.trim() || null,
          dedupeEmail: editingFeed.dedupeEmail,
          dedupeLandline: editingFeed.dedupeLandline,
          dedupeCellphone: editingFeed.dedupeCellphone,
          dedupeAcross: editingFeed.dedupeAcross,
          lookbackPeriod: parseInt(editingFeed.lookbackPeriod),
          filterTypeUrl: editingFeed.filterTypeUrl || null,
          filterUrl: editingFeed.filterUrl.filter((url) => url.trim()),
          filterState: editingFeed.filterState || null,
          filterStateChoice: editingFeed.filterStateChoice,
          filterZip: editingFeed.filterZip || null,
          filterZipCodes: editingFeed.filterZipCodes,
          rejectOldLeadsMaxAge: editingFeed.rejectOldLeadsMaxAge.trim() || null,
          pingTimeout: editingFeed.pingTimeout ? parseInt(editingFeed.pingTimeout) : null,
          dailyLimit: editingFeed.dailyLimit ? parseInt(editingFeed.dailyLimit) : null,
          chokePercent: editingFeed.chokePercent ? parseInt(editingFeed.chokePercent) : 0,
          costPerLead: editingFeed.costPerLead ? parseFloat(editingFeed.costPerLead) : null,
          salesperson: editingFeed.salesperson ? parseInt(editingFeed.salesperson) : null,
          notifications: editingFeed.notifications,
          notifyThresholdCount: editingFeed.notifyThresholdCount ? parseInt(editingFeed.notifyThresholdCount) : 0,
          notifyThresholdTime: editingFeed.notifyThresholdTime.trim() || null,
          notifyThresholdDays: editingFeed.notifyThresholdDays,
          pauseMessage: editingFeed.pauseMessage.trim() || null,
          timeskew: editingFeed.timeskew.trim() || null,
          status: editingFeed.status,
          minimumBirthAge: editingFeed.minimumBirthAge ? parseInt(editingFeed.minimumBirthAge) : null,
          maximumBirthAge: editingFeed.maximumBirthAge ? parseInt(editingFeed.maximumBirthAge) : null,
        };

        const response = await axios.put(`/api/inbound-feeds/${editingFeed.idFeedIn}`, payload);

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
      timezones,
      staffUsers,
      usStates,
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
      recordSearchLink,
      fetchFeeds,
      toggleCompanyFeeds,
      togglePause,
      openAddModal,
      closeAddModal,
      openEditModal,
      openApiSpec,
      openImportDataModal,
      openExportDataModal,
      openUrlReportModal,
      closeEditModal,
      updateNewFeed,
      updateEditingFeed,
      handleAddFeed,
      handleUpdateFeed,
      editingFeed,
      apiSpecModal,
      apiSpecLoading,
      copyToClipboard,
      closeApiSpecModal,
      importDataModal,
      importColumnOptions,
      importFileInput,
      importSending,
      onImportFileSelect,
      submitImport,
      closeImportDataModal,
      exportDataModal,
      exportColumns,
      exportSending,
      closeExportDataModal,
      submitExport,
      urlReportModal,
      urlReportResults,
      urlReportUrlList,
      urlReportLoading,
      closeUrlReportModal,
      runUrlReport,
      outgoingFeedsModal,
      outgoingFeedsPopulations,
      outgoingFeedsLoading,
      outboundFeedsForPopulation,
      availableOutboundFeedsForPopulation,
      pendingOutgoingFeeds,
      addToPendingOutgoingFeeds,
      removeFromPendingOutgoingFeeds,
      addOutgoingFeedModal,
      addOutgoingFeedModalError,
      addOutgoingFeedSaving,
      openOutgoingFeedsModal,
      closeOutgoingFeedsModal,
      openAddOutgoingFeedModal,
      closeAddOutgoingFeedModal,
      openEditOutgoingFeedModal,
      saveOutgoingFeed,
      toggleOutgoingFeedPopulation,
      deleteOutgoingFeedPopulation,
    };
  },
};
</script>

<style scoped>
.pt-2{
  padding-top:20px;
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

.status-active {
  color: #429038;
}

.status-hidden {
  color: #999;
}

.status-retired {
  color: #d9534f;
}

.incoming-col-large {
  width: 50%;
}

.record-search-link-cell a {
  color: inherit;
  text-decoration: none;
}

.record-search-link-cell a:hover {
  text-decoration: underline;
}

.incoming-col-small {
  width: 15%;
}

/* Add Outgoing Feed modal (matches OutgoingFeedsManagement population modal) */
.add-population-modal .modal-body {
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

.text-right {
  text-align: right;
}

.text-center {
  text-align: center;
}

.btn-primary {
  background-color: #429038;
  border-color: #429038;
}

.btn-primary:hover,
.btn-primary:focus {
  background-color: #357a2e;
  border-color: #357a2e;
}

/* Ensure dropdown and modals work correctly */
.dropdown-item {
  cursor: pointer;
}

/* Incoming feed modals - Teleport to body, visible when v-show is true */
.incoming-feed-modal {
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
.incoming-feed-modal .modal-dialog {
  margin: auto;
  background: #fff;
  border-radius: 4px;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
  max-width: 90%;
}

.modal-header {
  background: #072f5f;
  color: #fff;
}

.modal-header .close {
  color: #fff;
  opacity: 0.6;
}

.modal-header .close:hover {
  opacity: 1;
}

/* Toggle Switch */
.switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 34px;
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
  border-radius: 34px;
}

.slider:before {
  position: absolute;
  content: '';
  height: 26px;
  width: 26px;
  left: 4px;
  bottom: 4px;
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
</style>

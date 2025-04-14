$(document).ready(() => {
  // Load dashboard data
  loadDashboardData()

  // Load data for each tab
  $("#users-tab").on("shown.bs.tab", loadUsers)
  $("#regions-tab").on("shown.bs.tab", loadRegions)
  $("#atms-tab").on("shown.bs.tab", loadAtms)
  $("#transactions-tab").on("shown.bs.tab", loadTransactions)
  $("#alerts-tab").on("shown.bs.tab", loadAlerts)

  // Handle form submissions
  $("#save-user-btn").click(saveUser)
  $("#save-region-btn").click(saveRegion)
  $("#save-atm-btn").click(saveAtm)
})

// Load dashboard data
function loadDashboardData() {
  // Load counts
  $.getJSON("../api/admin/get_counts.php", (data) => {
    $("#total-users").text(data.users)
    $("#total-transactions").text(data.transactions)
    $("#total-alerts").text(data.alerts)
  })

  // Load recent transactions
  $.getJSON("../api/admin/get_recent_transactions.php", (data) => {
    let html = ""
    if (data.length === 0) {
      html = '<tr><td colspan="5">No transactions found</td></tr>'
    } else {
      data.forEach((transaction) => {
        html += `<tr>
                    <td>${transaction.transaction_id}</td>
                    <td>${transaction.full_name}</td>
                    <td>$${Number.parseFloat(transaction.amount).toFixed(2)}</td>
                    <td><span class="badge bg-${getStatusBadgeClass(transaction.transaction_status)}">${transaction.transaction_status}</span></td>
                    <td>${formatDate(transaction.transaction_date)}</td>
                </tr>`
      })
    }
    $("#recent-transactions-table tbody").html(html)
  })

  // Load recent alerts
  $.getJSON("../api/admin/get_recent_alerts.php", (data) => {
    let html = ""
    if (data.length === 0) {
      html = '<tr><td colspan="5">No alerts found</td></tr>'
    } else {
      data.forEach((alert) => {
        html += `<tr>
                    <td>${alert.alert_id}</td>
                    <td>${alert.transaction_id}</td>
                    <td>${alert.alert_type}</td>
                    <td><span class="badge bg-${getAlertStatusBadgeClass(alert.alert_status)}">${alert.alert_status}</span></td>
                    <td><span class="badge bg-${getResponseBadgeClass(alert.user_response)}">${alert.user_response}</span></td>
                </tr>`
      })
    }
    $("#recent-alerts-table tbody").html(html)
  })
}

// Load users
function loadUsers() {
  $.getJSON("../api/admin/get_users.php", (data) => {
    let html = ""
    if (data.length === 0) {
      html = '<tr><td colspan="6">No users found</td></tr>'
    } else {
      data.forEach((user) => {
        html += `<tr>
                    <td>${user.user_id}</td>
                    <td>${user.full_name}</td>
                    <td>${user.phone_number}</td>
                    <td>${user.email}</td>
                    <td>${formatDate(user.created_at)}</td>
                    <td>
                        <button class="btn btn-sm btn-primary edit-user" data-id="${user.user_id}">Edit</button>
                        <button class="btn btn-sm btn-danger delete-user" data-id="${user.user_id}">Delete</button>
                    </td>
                </tr>`
      })
    }
    $("#users-table tbody").html(html)

    // Also populate the user dropdown for regions
    let options = '<option value="">Select User</option>'
    data.forEach((user) => {
      options += `<option value="${user.user_id}">${user.full_name}</option>`
    })
    $("#region-user").html(options)
  })
}

// Load regions
function loadRegions() {
  $.getJSON("../api/admin/get_regions.php", (data) => {
    let html = ""
    if (data.length === 0) {
      html = '<tr><td colspan="6">No regions found</td></tr>'
    } else {
      data.forEach((region) => {
        html += `<tr>
                    <td>${region.region_id}</td>
                    <td>${region.full_name}</td>
                    <td>${region.region_name}</td>
                    <td>${region.center_latitude}, ${region.center_longitude}</td>
                    <td>${region.radius_km}</td>
                    <td>
                        <button class="btn btn-sm btn-primary edit-region" data-id="${region.region_id}">Edit</button>
                        <button class="btn btn-sm btn-danger delete-region" data-id="${region.region_id}">Delete</button>
                    </td>
                </tr>`
      })
    }
    $("#regions-table tbody").html(html)
  })
}

// Load ATMs
function loadAtms() {
  $.getJSON("../api/admin/get_atms.php", (data) => {
    let html = ""
    if (data.length === 0) {
      html = '<tr><td colspan="5">No ATMs found</td></tr>'
    } else {
      data.forEach((atm) => {
        html += `<tr>
                    <td>${atm.atm_id}</td>
                    <td>${atm.atm_name}</td>
                    <td>${atm.latitude}, ${atm.longitude}</td>
                    <td>${atm.address}</td>
                    <td>
                        <button class="btn btn-sm btn-primary edit-atm" data-id="${atm.atm_id}">Edit</button>
                        <button class="btn btn-sm btn-danger delete-atm" data-id="${atm.atm_id}">Delete</button>
                    </td>
                </tr>`
      })
    }
    $("#atms-table tbody").html(html)
  })
}

// Load transactions
function loadTransactions() {
  $.getJSON("../api/admin/get_transactions.php", (data) => {
    let html = ""
    if (data.length === 0) {
      html = '<tr><td colspan="7">No transactions found</td></tr>'
    } else {
      data.forEach((transaction) => {
        html += `<tr>
                    <td>${transaction.transaction_id}</td>
                    <td>${transaction.full_name}</td>
                    <td>${transaction.atm_name}</td>
                    <td>$${Number.parseFloat(transaction.amount).toFixed(2)}</td>
                    <td>${transaction.transaction_type}</td>
                    <td><span class="badge bg-${getStatusBadgeClass(transaction.transaction_status)}">${transaction.transaction_status}</span></td>
                    <td>${formatDate(transaction.transaction_date)}</td>
                </tr>`
      })
    }
    $("#transactions-table tbody").html(html)
  })
}

// Load alerts
function loadAlerts() {
  $.getJSON("../api/admin/get_alerts.php", (data) => {
    let html = ""
    if (data.length === 0) {
      html = '<tr><td colspan="7">No alerts found</td></tr>'
    } else {
      data.forEach((alert) => {
        html += `<tr>
                    <td>${alert.alert_id}</td>
                    <td>${alert.transaction_id}</td>
                    <td>${alert.alert_type}</td>
                    <td>${alert.alert_message}</td>
                    <td><span class="badge bg-${getAlertStatusBadgeClass(alert.alert_status)}">${alert.alert_status}</span></td>
                    <td><span class="badge bg-${getResponseBadgeClass(alert.user_response)}">${alert.user_response}</span></td>
                    <td>${formatDate(alert.created_at)}</td>
                </tr>`
      })
    }
    $("#alerts-table tbody").html(html)
  })
}

// Save new user
function saveUser() {
  const userData = {
    full_name: $("#user-name").val(),
    phone_number: $("#user-phone").val(),
    email: $("#user-email").val(),
  }

  $.ajax({
    url: "../api/admin/add_user.php",
    type: "POST",
    contentType: "application/json",
    data: JSON.stringify(userData),
    success: (response) => {
      $("#addUserModal").modal("hide")
      $("#add-user-form")[0].reset()
      loadUsers()
      alert("User added successfully!")
    },
    error: (xhr) => {
      alert("Error: " + xhr.responseJSON.message)
    },
  })
}

// Save new region
function saveRegion() {
  const regionData = {
    user_id: $("#region-user").val(),
    region_name: $("#region-name").val(),
    center_latitude: $("#region-lat").val(),
    center_longitude: $("#region-lng").val(),
    radius_km: $("#region-radius").val(),
  }

  $.ajax({
    url: "../api/admin/add_region.php",
    type: "POST",
    contentType: "application/json",
    data: JSON.stringify(regionData),
    success: (response) => {
      $("#addRegionModal").modal("hide")
      $("#add-region-form")[0].reset()
      loadRegions()
      alert("Region added successfully!")
    },
    error: (xhr) => {
      alert("Error: " + xhr.responseJSON.message)
    },
  })
}

// Save new ATM
function saveAtm() {
  const atmData = {
    atm_name: $("#atm-name").val(),
    latitude: $("#atm-lat").val(),
    longitude: $("#atm-lng").val(),
    address: $("#atm-address").val(),
  }

  $.ajax({
    url: "../api/admin/add_atm.php",
    type: "POST",
    contentType: "application/json",
    data: JSON.stringify(atmData),
    success: (response) => {
      $("#addAtmModal").modal("hide")
      $("#add-atm-form")[0].reset()
      loadAtms()
      alert("ATM added successfully!")
    },
    error: (xhr) => {
      alert("Error: " + xhr.responseJSON.message)
    },
  })
}

// Helper functions
function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleString()
}

function getStatusBadgeClass(status) {
  switch (status) {
    case "APPROVED":
      return "success"
    case "DENIED":
      return "danger"
    case "PENDING":
      return "warning"
    case "FLAGGED":
      return "danger"
    default:
      return "secondary"
  }
}

function getAlertStatusBadgeClass(status) {
  switch (status) {
    case "SENT":
      return "info"
    case "DELIVERED":
      return "success"
    case "FAILED":
      return "danger"
    default:
      return "secondary"
  }
}

function getResponseBadgeClass(response) {
  switch (response) {
    case "APPROVED":
      return "success"
    case "DENIED":
      return "danger"
    case "NO_RESPONSE":
      return "warning"
    default:
      return "secondary"
  }
}

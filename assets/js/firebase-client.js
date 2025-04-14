// Import Firebase modules (if using modules)
import firebase from "firebase/app"
import "firebase/firestore"
import "firebase/auth"

// Firebase client-side integration
const firebaseConfig = {
  apiKey: "",
  authDomain: "",
  projectId: "",
  storageBucket: "",
  messagingSenderId: "",
  appId: "",
  measurementId: "",
}

// Initialize Firebase
firebase.initializeApp(firebaseConfig)
const db = firebase.firestore()
const auth = firebase.auth()

// User authentication state observer
auth.onAuthStateChanged((user) => {
  if (user) {
    // User is signed in
    console.log("User is signed in:", user.uid)

    // Store user ID in session storage
    sessionStorage.setItem("firebase_uid", user.uid)

    // Update user's online status
    updateUserStatus(user.uid, true)

    // Listen for user's transactions
    listenForTransactions(user.uid)
  } else {
    // User is signed out
    console.log("User is signed out")

    // Clear session storage
    const previousUid = sessionStorage.getItem("firebase_uid")
    if (previousUid) {
      updateUserStatus(previousUid, false)
    }
    sessionStorage.removeItem("firebase_uid")
  }
})

// Update user's online status
function updateUserStatus(uid, isOnline) {
  const userStatusRef = db.collection("users").doc(uid)

  userStatusRef.set(
    {
      online: isOnline,
      lastSeen: firebase.firestore.FieldValue.serverTimestamp(),
    },
    { merge: true },
  )
}

// Listen for user's transactions
function listenForTransactions(uid) {
  db.collection("users")
    .doc(uid)
    .collection("transactions")
    .orderBy("timestamp", "desc")
    .onSnapshot((snapshot) => {
      snapshot.docChanges().forEach((change) => {
        if (change.type === "added") {
          const transaction = change.doc.data()

          // Check if transaction is flagged
          if (transaction.status === "FLAGGED") {
            showTransactionAlert(transaction)
          }
        }
      })
    })
}

// Show transaction alert
function showTransactionAlert(transaction) {
  // Check if browser supports notifications
  if (!("Notification" in window)) {
    console.log("This browser does not support desktop notification")
    return
  }

  // Check if permission is already granted
  if (Notification.permission === "granted") {
    createNotification(transaction)
  }
  // Otherwise, ask for permission
  else if (Notification.permission !== "denied") {
    Notification.requestPermission().then((permission) => {
      if (permission === "granted") {
        createNotification(transaction)
      }
    })
  }
}

// Create notification
function createNotification(transaction) {
  const options = {
    body: `Transaction of $${transaction.amount} flagged for verification.`,
    icon: "/assets/images/logo.png",
    vibrate: [200, 100, 200],
    tag: `transaction-${transaction.transaction_id}`,
  }

  const notification = new Notification("Fraud Alert", options)

  notification.onclick = () => {
    window.focus()
    window.location.href = `/user/transaction-details.php?id=${transaction.transaction_id}`
  }
}

// Register service worker for push notifications
if ("serviceWorker" in navigator) {
  navigator.serviceWorker
    .register("/service-worker.js")
    .then((registration) => {
      console.log("Service Worker registered with scope:", registration.scope)
    })
    .catch((error) => {
      console.error("Service Worker registration failed:", error)
    })
}

// Using ESM imports for React
import React from 'https://esm.sh/react@18.2.0';

const VideoDrawer = function({ lemma, video, origin, isOpen, contentId, plainText, preserveNewlines = false }) {
  const [ngtText, setNgtText] = React.useState('');
  const [localPlainText, setLocalPlainText] = React.useState(plainText || '');
  const [lemmasWithSign, setLemmasWithSign] = React.useState([]);
  const [lemmasWithoutSign, setLemmasWithoutSign] = React.useState([]);
  const [isRecording, setIsRecording] = React.useState(false);
  const [countdown, setCountdown] = React.useState(0);
  const [recordedVideos, setRecordedVideos] = React.useState([]);
  const [fullscreenVideo, setFullscreenVideo] = React.useState(null);
  const [status, setStatus] = React.useState(null);
  const [showLemmasWithSign, setShowLemmasWithSign] = React.useState(false);
  const [showLemmasWithoutSign, setShowLemmasWithoutSign] = React.useState(true);
  const [activeDropdown, setActiveDropdown] = React.useState(null);
  const [themas, setThemas] = React.useState([]);
  const [addedLemmas, setAddedLemmas] = React.useState([]);
  const [glossSearchTerm, setGlossSearchTerm] = React.useState('');
  const [searchResults, setSearchResults] = React.useState([]);
  const [isSearching, setIsSearching] = React.useState(false);
  const [showNewGlossForm, setShowNewGlossForm] = React.useState(false);
  const [newGlossThema, setNewGlossThema] = React.useState('');

  const videoRef = React.useRef(null);
  const fullscreenVideoRef = React.useRef(null);
  const mediaRecorderRef = React.useRef(null);
  const streamRef = React.useRef(null);
  const chunksRef = React.useRef([]);
  const isRecordingRef = React.useRef(isRecording);

  // Load NGT text and lemmas on component mount
  React.useEffect(() => {
    isRecordingRef.current = isRecording;

    if (isOpen) {
      if (lemma) {
        fetchNgtTextByLemma();
      } else if (contentId) {
        fetchNgtTextByContentId();
      }
      
      // Fetch themas for dropdown
      fetchThemas();
    }
  }, [isOpen, lemma, contentId, isRecording]);
  
  // Fetch themas from API
  const fetchThemas = async () => {
    try {
      const response = await fetch('/hh/api.php?action=get_themas');
      const data = await response.json();
      
      if (Array.isArray(data)) {
        setThemas(data);
      } else {
        console.error('Failed to fetch themas: Invalid data format');
      }
    } catch (error) {
      console.error('Failed to fetch themas:', error);
    }
  };
  
  // Effect for handling fullscreen video
  React.useEffect(() => {
    if (fullscreenVideo && fullscreenVideoRef.current) {
      try {
        if (fullscreenVideoRef.current.requestFullscreen) {
          fullscreenVideoRef.current.requestFullscreen().catch(e => console.error('Could not enter fullscreen mode:', e));
        } else if (fullscreenVideoRef.current.webkitRequestFullscreen) {
          fullscreenVideoRef.current.webkitRequestFullscreen();
        } else if (fullscreenVideoRef.current.msRequestFullscreen) {
          fullscreenVideoRef.current.msRequestFullscreen();
        }
      } catch (error) {
        console.error('Error requesting fullscreen:', error);
      }
    }
  }, [fullscreenVideo]);
  
  // Add event listener for ESC key to close modal
  React.useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && fullscreenVideo) {
        setFullscreenVideo(null);
      }
    };
    
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [fullscreenVideo]);
  
  const fetchNgtTextByLemma = async () => {
    try {
      const response = await fetch(`/hh/api.php?action=get_ngt_text&lemma=${encodeURIComponent(lemma)}`);
      const data = await response.json();
      
      if (data.error) {
        console.error('Error fetching NGT text:', data.error);
        return;
      }
      
      setNgtText(data.ngt_text || '');
      setLocalPlainText(data.plain_text || '');
      console.log(data.plain_text)

      // Fetch lemmas based on the plain text
      if (data.plain_text) {
        console.log(data.plain_text)
        fetchLemmas(data.plain_text);
      }
    } catch (error) {
      console.error('Failed to fetch NGT text:', error);
    }
  };
  
  const fetchNgtTextByContentId = async () => {
    try {
      const response = await fetch(`/hh/api.php?action=get_content_ngt_text&id=${encodeURIComponent(contentId)}`);
      const data = await response.json();
      
      if (data.error) {
        console.error('Error fetching NGT text:', data.error);
        return;
      }
      
      setNgtText(data.ngt_text || '');
      
      // If plain text wasn't provided, fetch it
      if (!localPlainText) {
        setLocalPlainText(data.plain_text || '');
      }
      
      // Fetch lemmas based on the plain text
      if (localPlainText || data.plain_text) {
        fetchLemmas(localPlainText || data.plain_text);
      }
      
      // Set recorded videos if available
      if (data.recorded_videos && Array.isArray(data.recorded_videos)) {
        setRecordedVideos(data.recorded_videos.map(filename => ({
          url: `/uploads/${filename}`,
          filename
        })));
      }
      
      // Set status
      setStatus(data.status);
    } catch (error) {
      console.error('Failed to fetch NGT text:', error);
    }
  };
  
  const fetchLemmas = async (text) => {
    try {
      console.log(text)
      const response = await fetch(`/hh/api.php?action=get_lemmas&text=${encodeURIComponent(text)}`);
      const data = await response.json();
      
      if (data.error) {
        console.error('Error fetching lemmas:', data.error);
        return;
      }
      
      console.log(data)
      setLemmasWithSign(data.lemmas_with_sign || []);
      setLemmasWithoutSign(data.lemmas_without_sign || []);
    } catch (error) {
      console.error('Failed to fetch lemmas:', error);
    }
  };
  
  const handleNgtTextChange = (e) => {
    setNgtText(e.target.value);
  };
  
  const saveNgtText = async () => {
    try {
      let endpoint = lemma ? 'update_ngt_text' : 'update_content_ngt_text';
      let payload = lemma ? { lemma, ngt_text: ngtText } : { id: contentId, ngt_text: ngtText };
      
      const response = await fetch(`/hh/api.php?action=${endpoint}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload)
      });
      
      const data = await response.json();
      
      if (data.success) {
        // alert('NGT text updated successfully!');
      } else {
        console.error('Failed to update NGT text:', data.error);
        alert('Failed to update NGT text. Please try again.');
      }
    } catch (error) {
      console.error('Failed to update NGT text:', error);
      alert('Failed to update NGT text. Please try again.');
    }
  };
  
  // Toggle dropdown for lemma
  const toggleDropdown = (lemma) => {
    if (activeDropdown === lemma) {
      setActiveDropdown(null);
    } else {
      setActiveDropdown(lemma);
    }
  };
  
  // Add lemma to form_data with selected thema
  const addLemmaToThema = async (lemma, thema) => {
    try {
      const formData = new FormData();
      formData.append('wordList', lemma);
      formData.append('thema', thema);
      formData.append('userId', '6'); // Add required userId parameter
      //we want to add TydBase and HealthHolland as json array to formdata labels
      formData.append('labels', JSON.stringify(['TYDbase', 'HealthHolland']));

      
      // Optional parameters that may be needed based on batch_add.php updates
      formData.append('checkDuplicatesWithSuffix', 'false');

      console.log(formData)
      
      const response = await fetch('/menu_beta/batch_add.php', {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      if (data.success && data.success.length > 0) {
        // Add to added lemmas list to mark as added
        setAddedLemmas([...addedLemmas, lemma]);
        setActiveDropdown(null); // Close dropdown after adding
        
        // Also show the actual word that was added (may have suffix)
        const addedWord = data.success[0];
        if (addedWord !== lemma) {
          alert(`Gloss added as: ${addedWord}`);
        }
        
        return true;
      } else if (data.duplicatesWithSuffix && data.duplicatesWithSuffix.length > 0) {
        // Handle duplicate with suffix case
        alert(`This gloss already exists with a suffix. Please use a different name.`);
        return false;
      } else if (data.specialChars && data.specialChars.length > 0) {
        // Handle special characters case
        alert(`The gloss contains special characters that are not allowed.`);
        return false;
      } else if (data.errors && data.errors.length > 0) {
        console.error('Failed to add lemma:', data.errors);
        alert(`Failed to add lemma: ${data.errors.join(', ')}`);
        return false;
      } else if (data.error) {
        // Handle case where there's a single error string
        console.error('Failed to add lemma:', data.error);
        alert(`Failed to add lemma: ${data.error}`);
        return false;
      } else {
        console.error('Unknown error while adding lemma');
        alert('Unknown error while adding lemma. Please try again.');
        return false;
      }
    } catch (error) {
      console.error('Failed to add lemma:', error);
      alert('Failed to add lemma. Please try again.');
      return false;
    }
  };

  const startRecording = async () => {
    try {
      // Start countdown
      setCountdown(3);
      
      // Wait for countdown
      for (let i = 3; i > 0; i--) {
        setCountdown(i);
        await new Promise(resolve => setTimeout(resolve, 1000));
      }
      
      // Get user media
      const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
      streamRef.current = stream;
      
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        videoRef.current.play();
      }
      
      // Start recording
      const mediaRecorder = new MediaRecorder(stream);
      mediaRecorderRef.current = mediaRecorder;
      
      mediaRecorder.ondataavailable = (e) => {
        if (e.data.size > 0) {
          chunksRef.current.push(e.data);
        }
        setIsRecording(true);

      };
      
      mediaRecorder.onstop = async () => {
        const blob = new Blob(chunksRef.current, { type: 'video/webm' });
        chunksRef.current = [];
        
        // Upload the video
        await uploadVideo(blob);
        
        // Clean up
        if (streamRef.current) {
          streamRef.current.getTracks().forEach(track => track.stop());
        }
        
        if (videoRef.current) {
          videoRef.current.srcObject = null;
        }
        
        setIsRecording(false);
      };
      
      // Start recording
      mediaRecorder.start();
      setIsRecording(true);
      setCountdown(0);
      
      // Set up spacebar listener to stop recording
      const handleKeyDown = (e) => {
        console.log('Key pressed:', e.code, isRecordingRef.current);
        if (e.code === 'Space' && isRecordingRef.current) {
          e.preventDefault();
          stopRecording();
          document.removeEventListener('keydown', handleKeyDown);
        }
      };
      
      document.addEventListener('keydown', handleKeyDown);
    } catch (error) {
      console.error('Error starting recording:', error);
      setIsRecording(false);
      setCountdown(0);
      alert('Failed to start recording. Please make sure your camera is connected and you have granted permissions.');
    }
  };
  
  const stopRecording = () => {
    if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
      mediaRecorderRef.current.stop();
    }
  };
  
  const uploadVideo = async (blob) => {
    try {
      const formData = new FormData();
      formData.append('video', blob, 'captured_video.webm');
      
      // Add identifier based on context - either lemma or content ID
      if (lemma) {
        formData.append('lemma', lemma);
      } else {
        formData.append('content_id', contentId);
      }
      
      const response = await fetch('/hh/api.php?action=upload_video', {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Add to recordedVideos
        setRecordedVideos(prev => [...prev, {
          url: data.url,
          filename: data.filename
        }]);
        
        // alert('Video uploaded successfully!');
      } else {
        console.error('Failed to upload video:', data.error);
        alert('Failed to upload video. Please try again.');
      }
    } catch (error) {
      console.error('Failed to upload video:', error);
      alert('Failed to upload video. Please try again.');
    }
  };
  
  // Add method to delete a recorded video
  const deleteRecordedVideo = async (videoFilename, index) => {
    if (!confirm('Are you sure you want to remove this video from the list?')) {
      return;
    }
    
    try {
      const response = await fetch('/hh/api.php?action=delete_recorded_video', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          id: contentId,
          filename: videoFilename
        })
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Remove the video from the local state
        setRecordedVideos(prev => prev.filter((_, i) => i !== index));
        // alert('Video removed successfully');
      } else {
        console.error('Failed to remove video:', data.error);
        alert('Failed to remove video. Please try again.');
      }
    } catch (error) {
      console.error('Failed to remove video:', error);
      alert('Failed to remove video. Please try again.');
    }
  };
  
  // Add method to toggle status
  const toggleStatus = async () => {
    try {
      // If status is truthy, set it to null (not 0), otherwise set it to 1
      const newStatus = status ? null : 1;
      
      const response = await fetch('/hh/api.php?action=update_content_status', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          id: contentId,
          status: newStatus === null ? 'null' : newStatus
        })
      });
      
      const data = await response.json();
      
      if (data.success) {
        setStatus(data.status);
        // alert(`Status updated to ${newStatus === 1 ? 'Klaar' : 'Niet Klaar'}`);
      } else {
        console.error('Failed to update status:', data.error);
        alert('Failed to update status. Please try again.');
      }
    } catch (error) {
      console.error('Failed to update status:', error);
      alert('Failed to update status. Please try again.');
    }
  };
  
  // Open video in fullscreen modal
  const openFullscreenVideo = (videoUrl) => {
    setFullscreenVideo(videoUrl);
  };
  
  // Close fullscreen modal
  const closeFullscreenModal = () => {
    setFullscreenVideo(null);
  };
  
  // Render fullscreen video modal
  const renderFullscreenModal = () => {
    if (!fullscreenVideo) return null;
    
    return React.createElement('div', {
      style: {
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        backgroundColor: 'rgba(0, 0, 0, 0.9)',
        zIndex: 9999,
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        alignItems: 'center'
      }
    }, 
    React.createElement('button', {
      onClick: closeFullscreenModal,
      style: {
        position: 'absolute',
        top: '20px',
        right: '20px',
        backgroundColor: 'transparent',
        color: 'white',
        border: 'none',
        fontSize: '24px',
        cursor: 'pointer',
        zIndex: 10000
      }
    }, '×'),
    React.createElement('video', {
      ref: fullscreenVideoRef,
      src: fullscreenVideo,
      controls: true,
      autoPlay: true,
      style: {
        maxWidth: '90%',
        maxHeight: '90%'
      }
    }),
    React.createElement('div', {
      style: {
        marginTop: '10px',
        color: 'white',
        fontSize: '14px'
      }
    }, 'Press ESC to close')
    );
  };
  
  // Render the thema dropdown
  const renderThemaDropdown = (lemmaItem) => {
    if (activeDropdown !== lemmaItem.lemma) return null;
    
    return React.createElement('div', {
      style: {
        position: 'absolute',
        zIndex: 1000,
        backgroundColor: 'white',
        border: '1px solid #ccc',
        borderRadius: '4px',
        boxShadow: '0 2px 8px rgba(0, 0, 0, 0.15)',
        maxHeight: '200px',
        overflowY: 'auto',
        width: '250px',
        padding: '5px'
      }
    },
    React.createElement('div', {
      style: {
        padding: '5px',
        borderBottom: '1px solid #eee',
        fontWeight: 'bold'
      }
    }, 'Select Thema:'),
    themas.map((thema, idx) => 
      React.createElement('div', {
        key: idx,
        onClick: () => addLemmaToThema(lemmaItem.lemma, thema),
        style: {
          padding: '5px',
          cursor: 'pointer',
          fontSize: '14px',
          borderBottom: idx < themas.length - 1 ? '1px solid #eee' : 'none'
        },
        onMouseOver: (e) => e.target.style.backgroundColor = '#f0f0f0',
        onMouseOut: (e) => e.target.style.backgroundColor = 'transparent'
      }, thema)
    )
    );
  };

  // Search for glosses
  const searchGlosses = async (term) => {
    if (!term || term.length < 2) {
      setSearchResults([]);
      return;
    }
    
    setIsSearching(true);
    
    try {
      const response = await fetch(`/hh/getGlosses.php?action=search&term=${encodeURIComponent(term)}`);
      const data = await response.json();
      
      if (data.error) {
        console.error('Error searching glosses:', data.error);
        setSearchResults([]);
      } else {
        setSearchResults(data.results || []);
      }
    } catch (error) {
      console.error('Failed to search glosses:', error);
      setSearchResults([]);
    } finally {
      setIsSearching(false);
    }
  };
  
  // Debounce search
  React.useEffect(() => {
    const timer = setTimeout(() => {
      if (glossSearchTerm) {
        searchGlosses(glossSearchTerm);
      }
    }, 300);
    
    return () => clearTimeout(timer);
  }, [glossSearchTerm]);
  
  // Handle adding a new gloss
  const handleAddNewGloss = async () => {
    if (!glossSearchTerm || !newGlossThema) {
      alert('Both gloss term and thema are required');
      return;
    }
    
    try {
      const success = await addLemmaToThema(glossSearchTerm, newGlossThema);
      
      if (success) {
        // Reset form after successful add
        setGlossSearchTerm('');
        setNewGlossThema('');
        setShowNewGlossForm(false);
        
        // Add to added lemmas list
        setAddedLemmas(prevLemmas => {
          if (!prevLemmas.includes(glossSearchTerm)) {
            return [...prevLemmas, glossSearchTerm];
          }
          return prevLemmas;
        });
        
        // Optional: refresh lemmas
        if (localPlainText) {
          fetchLemmas(localPlainText);
        }
      }
    } catch (error) {
      console.error('Failed to add new gloss:', error);
      alert('Failed to add new gloss. Please try again.');
    }
  };
  
  // Handle selecting a gloss from search results
  const handleSelectGloss = async (gloss) => {
    try {
      // If thema dropdown is active, use that thema
      let themaToUse = activeDropdown ? activeDropdown : newGlossThema;
      
      if (!themaToUse) {
        alert('Please select a thema first');
        return;
      }
      
      const success = await addLemmaToThema(gloss.glos, themaToUse);
      
      if (success) {
        // Reset search after successful add
        setGlossSearchTerm('');
        setSearchResults([]);
        
        // Add to added lemmas list
        setAddedLemmas(prevLemmas => {
          if (!prevLemmas.includes(gloss.glos)) {
            return [...prevLemmas, gloss.glos];
          }
          return prevLemmas;
        });
        
        // Optional: refresh lemmas
        if (localPlainText) {
          fetchLemmas(localPlainText);
        }
      }
    } catch (error) {
      console.error('Failed to add gloss:', error);
      alert('Failed to add gloss. Please try again.');
    }
  };

  // Render gloss search component
  const renderGlossSearch = () => {
    return React.createElement(
      'div',
      { style: { marginTop: '20px', padding: '15px', backgroundColor: '#f8f9fa', borderRadius: '4px' } },
      React.createElement('h4', null, 'Search for Glosses'),
      
      // Search input
      React.createElement(
        'div',
        { style: { display: 'flex', marginBottom: '10px' } },
        React.createElement('input', {
          type: 'text',
          value: glossSearchTerm,
          onChange: (e) => setGlossSearchTerm(e.target.value),
          placeholder: 'Type to search for glosses...',
          style: { 
            flex: 1, 
            padding: '8px',
            border: '1px solid #ced4da',
            borderRadius: '4px'
          }
        })
      ),
      
      // Search results
      isSearching ? 
        React.createElement('div', null, 'Searching...') :
        (searchResults.length > 0 ? 
          React.createElement(
            'div',
            null,
            React.createElement('h5', null, 'Results:'),
            React.createElement(
              'div',
              { style: { maxHeight: '250px', overflowY: 'auto' } },
              searchResults.map((gloss, idx) => 
                React.createElement(
                  'div',
                  { 
                    key: idx,
                    style: {
                      padding: '8px',
                      marginBottom: '5px',
                      backgroundColor: 'white',
                      borderRadius: '4px',
                      border: '1px solid #dee2e6',
                      cursor: 'pointer'
                    },
                    onClick: () => handleSelectGloss(gloss)
                  },
                  React.createElement('div', null, 
                    React.createElement('strong', null, gloss.glos),
                    ' ',
                    React.createElement('span', { style: { color: '#6c757d' } }, 
                      '(', gloss.source, ')'
                    )
                  ),
                  gloss.sense && gloss.sense.length > 0 && 
                    React.createElement('div', { style: { fontSize: '0.9em' } }, 
                      Array.isArray(gloss.sense) ? gloss.sense.join(', ') : gloss.sense
                    ),
                  gloss.video && 
                    React.createElement('div', { style: { marginTop: '5px' } },
                      React.createElement('small', null, 'Video available')
                    )
                )
              )
            )
          ) : 
          (glossSearchTerm.length >= 2 ? 
            React.createElement(
              'div',
              null,
              React.createElement('p', null, 'No glosses found for "', glossSearchTerm, '"'),
              React.createElement(
                'button',
                { 
                  onClick: () => setShowNewGlossForm(true),
                  style: {
                    padding: '8px 12px',
                    backgroundColor: '#28a745',
                    color: 'white',
                    border: 'none',
                    borderRadius: '4px',
                    cursor: 'pointer'
                  }
                },
                'Add as New Gloss'
              )
            ) : 
            null
          )
        )
      ),
      
      // New gloss form
      showNewGlossForm && React.createElement(
        'div',
        { style: { marginTop: '15px', padding: '10px', backgroundColor: 'white', borderRadius: '4px', border: '1px solid #dee2e6' } },
        React.createElement('h5', null, 'Add New Gloss'),
        
        // Gloss term (uses the search term)
        React.createElement(
          'div',
          { style: { marginBottom: '10px' } },
          React.createElement('label', null, 'Gloss Term:'),
          React.createElement('div', { style: { fontWeight: 'bold' } }, glossSearchTerm)
        ),
        
        // Thema selection
        React.createElement(
          'div',
          { style: { marginBottom: '15px' } },
          React.createElement('label', null, 'Select Thema:'),
          React.createElement(
            'select',
            { 
              value: newGlossThema,
              onChange: (e) => setNewGlossThema(e.target.value),
              style: {
                width: '100%',
                padding: '6px',
                border: '1px solid #ced4da',
                borderRadius: '4px'
              }
            },
            React.createElement('option', { value: '' }, 'Select a thema...'),
            themas.map((thema, idx) => 
              React.createElement('option', { key: idx, value: thema }, thema)
            )
          )
        ),
        
        // Action buttons
        React.createElement(
          'div',
          { style: { display: 'flex', gap: '10px' } },
          React.createElement(
            'button',
            { 
              onClick: handleAddNewGloss,
              style: {
                padding: '8px 12px',
                backgroundColor: '#28a745',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer'
              }
            },
            'Add Gloss'
          ),
          React.createElement(
            'button',
            { 
              onClick: () => setShowNewGlossForm(false),
              style: {
                padding: '8px 12px',
                backgroundColor: '#6c757d',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer'
              }
            },
            'Cancel'
          )
        )
      )
    
  };

  // Main render function using React.createElement instead of JSX
  return React.createElement('div', 
    { className: "video-drawer", style: { padding: '20px', backgroundColor: '#f5f5f5' } },
    
    // Fullscreen modal
    renderFullscreenModal(),
    
    React.createElement('div', 
      { style: { display: 'flex', gap: '20px' } },
      
      // Left column - Text & Video capture
      React.createElement('div', 
        { style: { flex: 1 } },
        
        // Add status button at the top
        contentId && React.createElement('div', 
          { style: { marginBottom: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
          React.createElement('h4', { style: { margin: 0 } }, 'Status:'),
          React.createElement('button', 
            { 
              onClick: toggleStatus,
              style: { 
                padding: '8px 16px',
                backgroundColor: status ? '#28a745' : '#dc3545',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer',
                fontWeight: 'bold'
              } 
            }, 
            status ? 'Klaar' : 'Niet Klaar'
          )
        ),
        
        React.createElement('h4', null, 'Plain Text'),
        React.createElement('div', 
          { 
            className: preserveNewlines ? 'preserve-newlines' : '',
            style: { 
              padding: '10px', 
              backgroundColor: 'white', 
              marginBottom: '15px'
            } 
          }, 
          localPlainText
        ),
        
        React.createElement('h4', null, 'NGT Text'),
        React.createElement('textarea', {
          value: ngtText,
          onChange: handleNgtTextChange,
          style: { width: '100%', minHeight: '300px', marginBottom: '10px' }
        }),
        React.createElement('button', 
          { 
            onClick: saveNgtText,
            style: { marginBottom: '20px' }
          }, 
          'Save NGT Text'
        ),
        
        React.createElement('div', null,
          React.createElement('h4', null, 'Record Video'),
          
          countdown > 0 ? 
            React.createElement('div', 
              { style: { fontSize: '72px', textAlign: 'center', margin: '20px 0' } },
              countdown
            ) :
            React.createElement('button', 
              { 
                onClick: startRecording,
                disabled: isRecording,
                style: { marginBottom: '10px' }
              }, 
              'Start Capture'
            ),
          
          isRecording && 
            React.createElement('div', 
              { 
                style: { 
                  backgroundColor: 'rgba(255, 0, 0, 0.2)', 
                  padding: '20px', 
                  textAlign: 'center',
                  marginBottom: '10px' 
                } 
              },
              React.createElement('p', null, 'Recording in progress... Press spacebar to stop'),
              React.createElement('video', { 
                ref: videoRef, 
                style: { width: '100%', maxHeight: '300px' } 
              })
            ),
          
          recordedVideos.length > 0 &&
            React.createElement('div', null,
              React.createElement('h4', null, 'Recorded Videos'),
              React.createElement('div', 
                { style: { display: 'flex', flexWrap: 'wrap', gap: '10px' } },
                recordedVideos.map((video, index) => 
                  React.createElement('div', 
                    { 
                      key: index, 
                      style: { 
                        width: '150px',
                        position: 'relative',
                        marginBottom: '15px'
                      }
                    },
                    // Video container with click handler
                    React.createElement('div', {
                        style: { 
                          position: 'relative',
                          cursor: 'pointer'
                        },
                        onClick: () => openFullscreenVideo(video.url)
                      },
                      React.createElement('video', { 
                        src: video.url,
                        style: { width: '100%' } 
                      }),
                      React.createElement('div', {
                        style: {
                          position: 'absolute',
                          top: '50%',
                          left: '50%',
                          transform: 'translate(-50%, -50%)',
                          backgroundColor: 'rgba(0, 0, 0, 0.5)',
                          color: 'white',
                          padding: '5px 10px',
                          borderRadius: '4px',
                          fontSize: '14px'
                        }
                      }, 'Click to expand')
                    ),
                    // Delete button
                    React.createElement('button', {
                      onClick: (e) => {
                        e.stopPropagation(); // Prevent triggering the video click
                        deleteRecordedVideo(video.filename, index);
                      },
                      style: {
                        width: '100%',
                        marginTop: '5px',
                        padding: '4px',
                        backgroundColor: '#dc3545',
                        color: 'white',
                        border: 'none',
                        borderRadius: '4px',
                        cursor: 'pointer',
                        fontSize: '12px'
                      }
                    }, 'Delete')
                  )
                )
              )
            )
        )
      ),
      
      // Right column - Sign language videos
      React.createElement('div', 
        { style: { flex: 1 } },
        React.createElement('div', null,
          // Collapsible header for Lemmas with Sign
          React.createElement('div', {
            onClick: () => setShowLemmasWithSign(!showLemmasWithSign),
            style: { 
              display: 'flex', 
              justifyContent: 'space-between', 
              alignItems: 'center',
              cursor: 'pointer',
              padding: '8px',
              backgroundColor: '#e9ecef',
              borderRadius: '4px',
              marginBottom: '10px'
            }
          }, 
            React.createElement('h4', { style: { margin: 0 } }, 'Lemmas with Sign'),
            React.createElement('span', null, showLemmasWithSign ? '▼' : '►')
          ),
          
          // Content for Lemmas with Sign (collapsible)
          showLemmasWithSign && (
            lemmasWithSign.length > 0 ? 
              React.createElement('ul', 
                { style: { listStyleType: 'none', padding: 0 } },
                lemmasWithSign.map((item, idx) => 
                  React.createElement('li', 
                    { 
                      key: idx, 
                      style: { marginBottom: '10px' } 
                    },
                    React.createElement('strong', null, item.lemma),
                    React.createElement('div', {
                      style: { position: 'relative', cursor: 'pointer' },
                      onClick: () => openFullscreenVideo(item.video)
                    }, 
                      React.createElement('video', { 
                        src: item.video,
                        style: { width: '100%', marginTop: '5px' } 
                      }),
                      React.createElement('div', {
                        style: {
                          position: 'absolute',
                          top: '50%',
                          left: '50%',
                          transform: 'translate(-50%, -50%)',
                          backgroundColor: 'rgba(0, 0, 0, 0.5)',
                          color: 'white',
                          padding: '5px 10px',
                          borderRadius: '4px',
                          fontSize: '14px',
                          opacity: 0.7
                        }
                      }, 'Click to expand')
                    ),
                    item.origin && React.createElement('small', null, 'Source: ', item.origin)
                  )
                )
              ) :
              React.createElement('p', null, 'No lemmas with signs found')
          )
        ),
        
        React.createElement('div', null,
          // Collapsible header for Lemmas without Sign
          React.createElement('div', {
            onClick: () => setShowLemmasWithoutSign(!showLemmasWithoutSign),
            style: { 
              display: 'flex', 
              justifyContent: 'space-between', 
              alignItems: 'center',
              cursor: 'pointer',
              padding: '8px',
              backgroundColor: '#e9ecef',
              borderRadius: '4px',
              marginTop: '20px',
              marginBottom: '10px'
            }
          }, 
            React.createElement('h4', { style: { margin: 0 } }, 'Lemmas without Sign'),
            React.createElement('span', null, showLemmasWithoutSign ? '▼' : '►')
          ),
          
          // Content for Lemmas without Sign (collapsible)
          showLemmasWithoutSign && (
            lemmasWithoutSign.length > 0 ?
              React.createElement('ul', { style: { listStyleType: 'none', padding: 0 } },
                lemmasWithoutSign.map((item, idx) => 
                  React.createElement('li', { 
                    key: idx,
                    style: { 
                      position: 'relative',
                      margin: '8px 0',
                      padding: '5px',
                      cursor: 'pointer',
                      backgroundColor: activeDropdown === item.lemma ? '#f0f0f0' : 'transparent',
                      borderRadius: '4px'
                    }
                  }, 
                    React.createElement('div', {
                      onClick: () => toggleDropdown(item.lemma),
                      style: {
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center'
                      }
                    },
                      // Show bold text if lemma has been added
                      React.createElement('span', {
                        style: {
                          fontWeight: addedLemmas.includes(item.lemma) ? 'bold' : 'normal'
                        }
                      }, item.lemma),
                      // Dropdown icon
                      React.createElement('span', null, activeDropdown === item.lemma ? '▼' : '►')
                    ),
                    // Render thema dropdown when this lemma is active
                    renderThemaDropdown(item)
                  )
                )
              ) :
              React.createElement('p', null, 'No lemmas without signs found')
          )
        ),
        
        // Add the new gloss search component
        renderGlossSearch()
      )
    )
  );
};

export default VideoDrawer;
